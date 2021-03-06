<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Storefront\Exception\AddressNotFoundException;
use Shopware\Storefront\Exception\CustomerNotFoundException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class AccountService
{
    /**
     * @var RepositoryInterface
     */
    private $countryRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        RepositoryInterface $countryRepository,
        RepositoryInterface $customerAddressRepository,
        RepositoryInterface $customerRepository
    ) {
        $this->countryRepository = $countryRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function getCustomerByLogin(string $email, string $password, CheckoutContext $context): CustomerStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer.email', $email));
        // TODO NEXT-389 we have to check an option like "bind customer to touchpoint"
        // todo in this case we have to filter "customer.touchpointId is null or touchpointId = :current"

        $customers = $this->customerRepository->search($criteria, $context->getContext());

        if ($customers->count() === 0) {
            throw new CustomerNotFoundException($email);
        }

        /** @var CustomerStruct $customer */
        $customer = $customers->first();

        if (!password_verify($password, $customer->getPassword())) {
            throw new BadCredentialsException();
        }

        return $customer;
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    public function getCustomerByContext(CheckoutContext $context): CustomerStruct
    {
        $this->validateCustomer($context);

        return $context->getCustomer();
    }

    public function saveProfile(ProfileSaveRequest $profileSaveRequest, CheckoutContext $context)
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'firstName' => $profileSaveRequest->getFirstName(),
            'lastName' => $profileSaveRequest->getLastName(),
            'title' => $profileSaveRequest->getTitle(),
            'salutation' => $profileSaveRequest->getSalutation(),
            'birthday' => $profileSaveRequest->getBirthday(),
        ];

        foreach ($profileSaveRequest->getExtensions() as $key => $value) {
            $data[$key] = $value;
        }
        $data = array_filter($data);

        $this->customerRepository->update([$data], $context->getContext());
    }

    public function savePassword(PasswordSaveRequest $passwordSaveRequest, CheckoutContext $context)
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'password' => $passwordSaveRequest->getPassword(),
        ];

        foreach ($passwordSaveRequest->getExtensions() as $key => $value) {
            $data[$key] = $value;
        }
        $data = array_filter($data);

        $this->customerRepository->update([$data], $context->getContext());
    }

    public function saveEmail(EmailSaveRequest $emailSaveRequest, CheckoutContext $context)
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'email' => $emailSaveRequest->getEmail(),
        ];

        foreach ($emailSaveRequest->getExtensions() as $key => $value) {
            $data[$key] = $value;
        }
        $data = array_filter($data);

        $this->customerRepository->update([$data], $context->getContext());
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidUuidException
     */
    public function getAddressById(string $addressId, CheckoutContext $context): CustomerAddressStruct
    {
        return $this->validateAddressId($addressId, $context);
    }

    public function getCountryList(CheckoutContext $context): array
    {
        $criteria = new ReadCriteria([]);
        $criteria->addFilter(new TermQuery('country.active', true));

        /** @var CountryCollection $countries */
        $countries = $this->countryRepository->read($criteria, $context->getContext());

        $countries->sortCountryAndStates();

        return $countries->getElements();
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    public function getAddressesByCustomer(CheckoutContext $context): array
    {
        $this->validateCustomer($context);
        $customer = $context->getCustomer();
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer_address.customerId', $context->getCustomer()->getId()));

        /** @var CustomerAddressCollection $addresses */
        $addresses = $this->customerAddressRepository->search($criteria, $context->getContext())->getEntities();

        return $addresses->sortByDefaultAddress($customer)->getElements();
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException     */
    public function saveAddress(AddressSaveRequest $addressSaveRequest, CheckoutContext $context): string
    {
        $this->validateCustomer($context);

        if (!$addressSaveRequest->getId()) {
            $addressSaveRequest->setId(Uuid::uuid4()->getHex());
        } else {
            $this->validateAddressId($addressSaveRequest->getId(), $context)->getId();
        }

        $data = [
            'id' => $addressSaveRequest->getId(),
            'customerId' => $context->getCustomer()->getId(),
            'salutation' => $addressSaveRequest->getSalutation(),
            'firstName' => $addressSaveRequest->getFirstName(),
            'lastName' => $addressSaveRequest->getLastName(),
            'street' => $addressSaveRequest->getStreet(),
            'city' => $addressSaveRequest->getCity(),
            'zipcode' => $addressSaveRequest->getZipcode(),
            'countryId' => $addressSaveRequest->getCountryId(),
            'countryStateId' => $addressSaveRequest->getCountryStateId(),
            'company' => $addressSaveRequest->getCompany(),
            'department' => $addressSaveRequest->getDepartment(),
            'title' => $addressSaveRequest->getTitle(),
            'vatId' => $addressSaveRequest->getVatId(),
            'additionalAddressLine1' => $addressSaveRequest->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $addressSaveRequest->getAdditionalAddressLine2(),
        ];

        /* TODO pretty dangerous since extensions could not only overwrite all properties above
        *  but also could write all sub entities.
        */
        foreach ($addressSaveRequest->getExtensions() as $key => $value) {
            $data[$key] = $value;
        }
        $data = array_filter($data);

        $this->customerAddressRepository->upsert([$data], $context->getContext());

        return $addressSaveRequest->getId();
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function deleteAddress(string $addressId, CheckoutContext $context)
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);
        $this->customerAddressRepository->delete([['id' => $addressId]], $context->getContext());
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultBillingAddress(string $addressId, CheckoutContext $context)
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);

        $data = [
            'id' => $context->getCustomer()->getId(),
            'defaultBillingAddressId' => $addressId,
        ];
        $this->customerRepository->update([$data], $context->getContext());
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultShippingAddress(string $addressId, CheckoutContext $context)
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);

        $data = [
            'id' => $context->getCustomer()->getId(),
            'defaultShippingAddressId' => $addressId,
        ];
        $this->customerRepository->update([$data], $context->getContext());
    }

    public function createNewCustomer(RegistrationRequest $registrationRequest, CheckoutContext $context): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $billingAddressId = Uuid::uuid4()->getHex();

        $addresses = [];

        $addresses[] = array_filter([
            'id' => $billingAddressId,
            'customerId' => $customerId,
            'countryId' => $registrationRequest->getBillingCountry(),
            'salutation' => $registrationRequest->getSalutation(),
            'firstName' => $registrationRequest->getFirstName(),
            'lastName' => $registrationRequest->getLastName(),
            'street' => $registrationRequest->getBillingStreet(),
            'zipcode' => $registrationRequest->getBillingZipcode(),
            'city' => $registrationRequest->getBillingCity(),
            'phoneNumber' => $registrationRequest->getBillingPhone(),
            'vatId' => $registrationRequest->getBillingVatId(),
            'additionalAddressLine1' => $registrationRequest->getBillingAdditionalAddressLine1(),
            'additionalAddressLine2' => $registrationRequest->getBillingAdditionalAddressLine2(),
            'countryStateId' => $registrationRequest->getBillingCountryState(),
        ]);

        if ($registrationRequest->hasDifferentShippingAddress()) {
            $shippingAddressId = Uuid::uuid4()->getHex();
            $addresses[] = array_filter([
                'id' => $shippingAddressId,
                'customerId' => $customerId,
                'countryId' => $registrationRequest->getShippingCountry(),
                'salutation' => $registrationRequest->getShippingSalutation(),
                'firstName' => $registrationRequest->getShippingFirstName(),
                'lastName' => $registrationRequest->getShippingLastName(),
                'street' => $registrationRequest->getShippingStreet(),
                'zipcode' => $registrationRequest->getShippingZipcode(),
                'city' => $registrationRequest->getShippingCity(),
                'phoneNumber' => $registrationRequest->getShippingPhone(),
                'additionalAddressLine1' => $registrationRequest->getShippingAdditionalAddressLine1(),
                'additionalAddressLine2' => $registrationRequest->getShippingAdditionalAddressLine2(),
                'countryStateId' => $registrationRequest->getShippingCountryState(),
            ]);
        }

        // todo implement customer number generator
        $data = [
            'id' => $customerId,
            'touchpointId' => $context->getTouchpoint()->getId(),
            'groupId' => $context->getCurrentCustomerGroup()->getId(),
            'defaultPaymentMethodId' => $context->getPaymentMethod()->getId(),
            'number' => '123',
            'salutation' => $registrationRequest->getSalutation(),
            'firstName' => $registrationRequest->getFirstName(),
            'lastName' => $registrationRequest->getLastName(),
            'password' => $registrationRequest->getPassword(),
            'email' => $registrationRequest->getEmail(),
            'title' => $registrationRequest->getTitle(),
            'encoder' => 'bcrypt',
            'active' => true,
            'defaultBillingAddressId' => $billingAddressId,
            'defaultShippingAddressId' => $shippingAddressId ?? $billingAddressId,
            'addresses' => $addresses,
            'birthday' => $registrationRequest->getBirthday(),
        ];

        $data = array_filter($data);
        $this->customerRepository->create([$data], $context->getContext());

        return $customerId;
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    private function validateCustomer(CheckoutContext $context)
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidUuidException
     */
    private function validateAddressId(string $addressId, CheckoutContext $context): CustomerAddressStruct
    {
        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        /** @var CustomerAddressStruct $address */
        $address = $this->customerAddressRepository->read(new ReadCriteria([$addressId]), $context->getContext())->get($addressId);

        if (!$address || $address->getCustomerId() !== $context->getCustomer()->getId()) {
            throw new AddressNotFoundException($addressId);
        }

        return $address;
    }
}
