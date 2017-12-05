<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerAddress;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Country\Event\CountryState\CountryStateBasicLoadedEvent;
use Shopware\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerAddressBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customer_address.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CustomerAddressBasicCollection
     */
    protected $customerAddresses;

    public function __construct(CustomerAddressBasicCollection $customerAddresses, TranslationContext $context)
    {
        $this->context = $context;
        $this->customerAddresses = $customerAddresses;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCustomerAddresses(): CustomerAddressBasicCollection
    {
        return $this->customerAddresses;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->customerAddresses->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->customerAddresses->getCountries(), $this->context);
        }
        if ($this->customerAddresses->getCountryStates()->count() > 0) {
            $events[] = new CountryStateBasicLoadedEvent($this->customerAddresses->getCountryStates(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}