<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTransactionTokenFactory;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TokenFactoryTest extends KernelTestCase
{
    const PAYMENT_METHOD_INVOICE = '19D144FFE15F4772860D59FCA7F207C1';

    const COUNTRY_GERMANY = '20080911ffff4fffafffffff19830531';

    const COUNTRY_STATE_NRW = '9F834BAD88204D9896F31993624AC74C';
    /**
     * @var PaymentTransactionTokenFactory
     */
    protected $tokenFactory;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var RepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var RepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var RepositoryInterface
     */
    protected $orderTransactionRepository;

    /**
     * @var Connection
     */
    protected $connection;

    public function setUp()
    {
        self::bootKernel();

        $this->tokenFactory = self::$container->get(PaymentTransactionTokenFactory::class);
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->connection = self::$container->get(Connection::class);

        $this->orderRepository = self::$container->get('order.repository');
        $this->customerRepository = self::$container->get('customer.repository');
        $this->orderTransactionRepository = self::$container->get('order_transaction.repository');
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function testGenerateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->read(new ReadCriteria([$transactionId]), Context::createDefaultContext(
            Defaults::TENANT_ID));

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId),
            $context
        );

        $token = $this->connection->fetchAssoc('SELECT * FROM payment_token WHERE token = ?;', [$tokenIdentifier]);

        self::assertEquals($transactionId, Uuid::fromBytesToHex($token['order_transaction_id']));
        self::assertEquals($tokenIdentifier, $token['token']);
        self::assertGreaterThan(new \DateTime(), new \DateTime($token['expires']));
    }

    /**
     * @throws InvalidTokenException
     * @throws TokenExpiredException
     */
    public function testValidateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->read(new ReadCriteria([$transactionId]), Context::createDefaultContext(
            Defaults::TENANT_ID));

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId),
            $context
        );

        $token = $this->tokenFactory->validateToken($tokenIdentifier, $context);

        self::assertEquals($transactionId, $token->getTransactionId());
        self::assertEquals($tokenIdentifier, $token->getToken());
        self::assertGreaterThan(new \DateTime(), $token->getExpires());
    }

    /**
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     * @throws InvalidTokenException
     */
    public function testInvalidateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->read(new ReadCriteria([$transactionId]), Context::createDefaultContext(
            Defaults::TENANT_ID));
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId),
            $context
        );

        $success = $this->tokenFactory->invalidateToken($tokenIdentifier, $context);

        self::assertTrue($success);
    }

    private function prepare(): string
    {
        $customerId = $this->createCustomer($this->customerRepository, $this->context);
        $orderId = $this->createOrder($customerId, $this->orderRepository, $this->context);

        return $this->createTransaction($orderId, $this->orderTransactionRepository, $this->context);
    }

    private function createTransaction(
        string $orderId,
        RepositoryInterface $orderTransactionRepository,
        Context $context
    ): string {
        $id = Uuid::uuid4()->getHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => self::PAYMENT_METHOD_INVOICE,
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_OPEN,
            'amount' => new Price(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $orderTransactionRepository->upsert([$transaction], $context);

        return $id;
    }

    private function createOrder(
        string $customerId,
        RepositoryInterface $orderRepository,
        Context $context
    ) {
        $orderId = Uuid::uuid4()->getHex();

        $order = [
            'id' => $orderId,
            'date' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            'amountTotal' => 100,
            'amountNet' => 100,
            'positionPrice' => 100,
            'shippingTotal' => 5,
            'shippingNet' => 5,
            'isNet' => true,
            'isTaxFree' => true,
            'customerId' => $customerId,
            'stateId' => Defaults::ORDER_STATE_OPEN,
            'paymentMethodId' => self::PAYMENT_METHOD_INVOICE,
            'currencyId' => Defaults::CURRENCY,
            'touchpointId' => Defaults::TOUCHPOINT,
            'billingAddress' => [
                'salutation' => 'mr',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => self::COUNTRY_GERMANY,
                'countryStateId' => self::COUNTRY_STATE_NRW,
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        $orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createCustomer(RepositoryInterface $repository, Context $context): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => self::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'touchpointId' => Defaults::TOUCHPOINT,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => self::COUNTRY_GERMANY,
                    'salutation' => 'Herr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $repository->upsert([$customer], $context);

        return $customerId;
    }
}
