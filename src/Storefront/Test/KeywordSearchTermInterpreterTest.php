<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use Shopware\Api\Search\Term\SearchTerm;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Storefront\Page\Search\KeywordSearchTermInterpreter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KeywordSearchTermInterpreterTest extends KernelTestCase
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @var KeywordSearchTermInterpreter
     */
    private $interpreter;

    public function setUp()
    {
        self::bootKernel();
        $container = self::$kernel->getContainer();

        $this->connection = $container->get('dbal_connection');
        $this->connection->beginTransaction();
        $this->interpreter = $container->get('shopware.storefront.search_term_interpreter');
        $this->connection->executeUpdate('DELETE FROM search_keyword');

        $this->setupKeywords();
    }

    public function tearDown()
    {
        $this->connection->rollBack();

        parent::tearDown();
    }

    /**
     * @dataProvider cases
     *
     * @param string $term
     * @param array  $expected
     */
    public function testMatching(string $term, array $expected)
    {
        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);

        $matches = $this->interpreter->interpret($term, $context);

        $keywords = array_map(function (SearchTerm $term) {
            return $term->getTerm();
        }, $matches->getTerms());

        self::assertEquals($expected, $keywords);
    }

    public function cases()
    {
        return [
            [
                'zeichn',
                ['zeichnet', 'zweichnet', 'ausgezeichnet', 'verkehrzeichennetzwerk', 'gezeichnet', 'zeichen'],
            ],
            [
                'zeichent',
                ['zeichnet', 'ausgezeichnet', 'verkehrzeichennetzwerk', 'gezeichnet', 'zeichen'],
            ],
            [
                'Büronetz',
                ['büronetzwerk'],
            ],
        ];
    }

    private function setupKeywords()
    {
        $keywords = [
            'zeichnet',
            'zweichnet',
            'ausgezeichnet',
            'verkehrzeichennetzwerk',
            'gezeichnet',
            'zeichen',
            'zweideutige',
            'zweier',
            'zweite',
            'zweiteilig',
            'zweiten',
            'zweites',
            'zweiweg',
            'zweifellos',
            'büronetzwerk',
            'heimnetzwerk',
            'netzwerk',
            'netzwerkadapter',
            'netzwerkbuchse',
            'netzwerkcontroller',
            'netzwerkdrucker',
            'netzwerke',
            'netzwerken',
            'netzwerkinfrastruktur',
            'netzwerkkabel',
            'netzwerkkabels',
            'netzwerkkarte',
            'netzwerklösung',
            'netzwerkschnittstelle',
            'netzwerkschnittstellen',
            'netzwerkspeicher',
            'netzwerkspeicherlösung',
            'netzwerkspieler',
            'schwarzweiß',
            'netzwerkprotokolle',
        ];

        foreach ($keywords as $keyword) {
            $this->connection->insert('search_keyword', [
                'keyword' => $keyword,
                'shop_uuid' => 'SWAG-SHOP-UUID-1',
                'document_count' => 1,
            ]);
        }
    }
}