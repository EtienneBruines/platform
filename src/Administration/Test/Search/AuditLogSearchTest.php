<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Search;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Search\AdministrationSearch;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuditLogSearchTest extends KernelTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var AdministrationSearch
     */
    private $search;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $userId;

    protected function setUp()
    {
        self::bootKernel();

        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();

        $this->productRepository = self::$container->get('product.repository');
        $this->search = self::$container->get(AdministrationSearch::class);
        $this->context = $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $this->connection->executeUpdate('
            DELETE FROM `version`;
            DELETE FROM `version_commit`;
            DELETE FROM `version_commit_data`;
            DELETE FROM `user`;
            DELETE FROM `order`;
            DELETE FROM `customer`;
            DELETE FROM `product`;
        ');

        $this->userId = Uuid::uuid4()->getHex();
        $this->context->getSourceContext()->setUserId($this->userId);

        $repo = self::$container->get('user.repository');
        $repo->upsert([
            [
                'id' => $this->userId,
                'localeId' => '20080911ffff4fffafffffff19830531',
                'name' => 'test-user',
                'username' => 'test-user',
                'email' => Uuid::uuid4()->getHex() . '@example.com',
                'password' => 'shopware',
            ],
        ], $context);

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testProductRanking()
    {
        $productId1 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

        $this->productRepository->upsert([
            ['id' => $productId1, 'name' => 'test product 1', 'price' => ['gross' => 10, 'net' => 9], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => $productId2, 'name' => 'test product 2', 'price' => ['gross' => 10, 'net' => 9], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => Uuid::uuid4()->getHex(), 'name' => 'notmatch', 'price' => ['gross' => 10, 'net' => 9], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
            ['id' => Uuid::uuid4()->getHex(), 'name' => 'notmatch', 'price' => ['gross' => 10, 'net' => 9], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
        ], $this->context);

        $result = $this->search->search('test product', 20, $this->context, $this->userId);

        /** @var ProductStruct $first */
        $first = $result['data'][0]['entity'];
        self::assertInstanceOf(ProductStruct::class, $first);

        /** @var ProductStruct $second */
        $second = $result['data'][1]['entity'];
        self::assertInstanceOf(ProductStruct::class, $second);

        $firstScore = $first->getExtension('search')->get('_score');
        $secondScore = $second->getExtension('search')->get('_score');

        self::assertSame($secondScore, $firstScore);

        $this->productRepository->update([
            ['id' => $productId2, 'price' => ['gross' => 15, 'net' => 1]],
            ['id' => $productId2, 'price' => ['gross' => 20, 'net' => 1]],
            ['id' => $productId2, 'price' => ['gross' => 25, 'net' => 1]],
            ['id' => $productId2, 'price' => ['gross' => 30, 'net' => 1]],
        ], $this->context);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId2, Defaults::LIVE_VERSION);
        $this->assertNotEmpty($changes);

        $result = $this->search->search('test product', 20, $this->context, $this->userId);

        self::assertCount(2, $result['data']);

        /** @var ProductStruct $first */
        $first = $result['data'][0]['entity'];
        self::assertInstanceOf(ProductStruct::class, $first);

        /** @var ProductStruct $second */
        $second = $result['data'][1]['entity'];
        self::assertInstanceOf(ProductStruct::class, $second);

        // `product-2` should now be boosted
        self::assertSame($first->getId(), $productId2);
        self::assertSame($second->getId(), $productId1);

        $firstScore = $result['data'][0]['_score'];
        $secondScore = $result['data'][1]['_score'];

        self::assertTrue($firstScore > $secondScore, print_r($firstScore . ' < ' . $secondScore, true));
    }

    private function getVersionData(string $entity, string $id, string $versionId): array
    {
        return $this->connection->fetchAll(
            "SELECT d.* 
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.id') = :id
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromStringToBytes($versionId),
            ]
        );
    }
}
