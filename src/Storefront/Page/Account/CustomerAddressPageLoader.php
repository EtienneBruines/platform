<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;

class CustomerAddressPageLoader
{
    /**
     * @var RepositoryInterface
     */
    private $customerAddressRepository;

    public function __construct(RepositoryInterface $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    public function load(CheckoutContext $context): CustomerAddressPageStruct
    {
        $criteria = $this->createCriteria($context->getCustomer()->getId());
        $addresses = $this->customerAddressRepository->search($criteria, $context->getContext());

        return new CustomerAddressPageStruct($addresses->sortByDefaultAddress($context->getCustomer()));
    }

    private function createCriteria(string $customerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer_address.customerId', $customerId));

        return $criteria;
    }
}
