<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Payment\Exception\PaymentMethodNotFoundHttpException;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Exception\AddressNotFoundException;
use Shopware\Storefront\Exception\CustomerNotFoundException;
use Shopware\Storefront\Page\Account\AccountService;
use Shopware\Storefront\Page\Account\AddressSaveRequest;
use Shopware\Storefront\Page\Account\CustomerAddressPageLoader;
use Shopware\Storefront\Page\Account\CustomerPageLoader;
use Shopware\Storefront\Page\Account\EmailSaveRequest;
use Shopware\Storefront\Page\Account\LoginRequest;
use Shopware\Storefront\Page\Account\OrderPageLoader;
use Shopware\Storefront\Page\Account\PasswordSaveRequest;
use Shopware\Storefront\Page\Account\ProfileSaveRequest;
use Shopware\Storefront\Page\Account\RegistrationRequest;
use Shopware\Storefront\Page\Checkout\PaymentMethodLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AccountController extends StorefrontController
{
    use TargetPathTrait;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    /**
     * @var CheckoutContextService
     */
    private $checkoutContextService;

    /**
     * @var PaymentMethodLoader
     */
    private $paymentMethodLoader;

    /**
     * @var OrderPageLoader
     */
    private $orderPageLoader;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var CustomerAddressPageLoader
     */
    private $customerAddressPageLoader;

    /**
     * @var CustomerPageLoader
     */
    private $customerPageLoader;

    public function __construct(
        CheckoutContextPersister $contextPersister,
        AccountService $accountService,
        CustomerAddressPageLoader $customerAddressPageLoader,
        CustomerPageLoader $customerPageLoader,
        CheckoutContextService $checkoutContextService,
        PaymentMethodLoader $paymentMethodLoader,
        OrderPageLoader $orderPageLoader
    ) {
        $this->contextPersister = $contextPersister;
        $this->accountService = $accountService;
        $this->customerAddressPageLoader = $customerAddressPageLoader;
        $this->customerPageLoader = $customerPageLoader;
        $this->checkoutContextService = $checkoutContextService;
        $this->paymentMethodLoader = $paymentMethodLoader;
        $this->orderPageLoader = $orderPageLoader;
    }

    /**
     * @Route("/account", name="account_home")
     */
    public function index(): Response
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('frontend/account/index.html.twig');
    }

    /**
     * @Route("/account/login", name="account_login")
     * @Method({"GET"})
     */
    public function login(Request $request, CheckoutContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->redirectToRoute('account_home');
        }

        return $this->renderStorefront('frontend/register/index.html.twig', [
            'redirectTo' => $request->get('redirectTo', $this->generateUrl('account_home')),
            'countryList' => $this->accountService->getCountryList($context),
        ]);
    }

    /**
     * @Route("/account/login", name="account_login_check", methods={"POST"})
     */
    public function checkLogin(LoginRequest $loginRequest, Request $request, CheckoutContext $context)
    {
        try {
            $customer = $this->accountService->getCustomerByLogin(
                $loginRequest->getEmail(),
                $loginRequest->getPassword(),
                $context
            );
        } catch (CustomerNotFoundException | BadCredentialsException $exception) {
            $this->addFlash('login_failure', 'Invalid credentials.');

            return $this->redirectToRoute('account_login');
        }

        $this->contextPersister->save(
            $context->getToken(),
            [CheckoutContextService::CUSTOMER_ID => $customer->getId()],
            $context->getTenantId()
        );

        $this->checkoutContextService->refresh($context->getTenantId(), $context->getTouchpoint()->getId(), $context->getToken());

        if ($url = $request->query->get('redirectTo')) {
            return $this->handleRedirectTo($url);
        }

        return $this->redirectToRoute('account_home');
    }

    /**
     * @Route("/account/logout", name="account_logout")
     */
    public function logout(CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->contextPersister->save(
            $context->getToken(),
            [CheckoutContextService::CUSTOMER_ID => null],
            $context->getTenantId()
        );

        return $this->redirectToRoute('homepage');
    }

    /**
     * @Route("/account/saveRegistration", name="account_save_registration")
     * @Method({"POST"})
     */
    public function saveRegistration(RegistrationRequest $registrationRequest, Request $request, CheckoutContext $context): Response
    {
        try {
            // todo validate user input
            $customerId = $this->accountService->createNewCustomer($registrationRequest, $context);

            $this->contextPersister->save(
                $context->getToken(),
                [CheckoutContextService::CUSTOMER_ID => $customerId],
                $context->getTenantId()
            );

            $this->checkoutContextService->refresh($context->getTenantId(), $context->getTouchpoint()->getId(), $context->getToken());
        } catch (BadCredentialsException $exception) {
            return $this->redirectToRoute('account_login');
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), 'storefront')) {
            return $this->redirect($targetPath);
        }

        return $this->redirectToRoute('account_home');
    }

    /**
     * @Route("/account/payment", name="account_payment", options={"seo"="false"})
     * @Method({"GET"})
     */
    public function paymentOverview(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('@Storefront/frontend/account/payment.html.twig', [
            'paymentMethods' => $this->paymentMethodLoader->load($request, $context->getContext()),
        ]);
    }

    /**
     * @Route("/account/savePayment", name="account_save_payment", options={"seo"="false"})
     * @Method({"POST"})
     */
    public function savePayment(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $data = $request->request->get('register');

        if (!array_key_exists('payment', $data) or !Uuid::isValid($data['payment'])) {
            throw new PaymentMethodNotFoundHttpException($data['payment']);
        }

        $this->contextPersister->save(
            $context->getToken(),
            [CheckoutContextService::PAYMENT_METHOD_ID => $data['payment']],
            $context->getTenantId()
        );

        return $this->redirectToRoute('account_home', ['success' => 'payment']);
    }

    /**
     * @Route("/account/orders", name="account_orders", options={"seo"="false"}, methods={"GET"})
     * @Method({"GET"})
     */
    public function orderOverview(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('@Storefront/frontend/account/orders.html.twig', [
            'orderPage' => $this->orderPageLoader->load($request, $context),
        ]);
    }

    /**
     * @Route("/account/profile", name="account_profile")
     */
    public function profileOverview(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo implement salutation
        // todo handle error messages
//        if ($request->query->get('errors')) {
//            foreach ($request->query->get('errors') as $error) {
//                $message = $this->View()->fetch('string:' . $error->getMessage());
//                $errorFlags[$error->getOrigin()->getName()] = true;
//                $errorMessages[] = $message;
//            }
//
//            $errorMessages = array_unique($errorMessages);
//        }

        return $this->renderStorefront('@Storefront/frontend/account/profile.html.twig', [
            'customerPage' => $this->customerPageLoader->load($context),
            'formData' => $request->request->get('formData', []),
            'errorFlags' => [],
            'errorMessages' => [],
            'success' => $request->query->get('success'),
            'section' => $request->query->get('section'),
        ]);
    }

    /**
     * @Route("/account/saveProfile", name="account_save_profile")
     * @Method({"POST"})
     */
    public function saveProfile(ProfileSaveRequest $profileSaveRequest, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->accountService->saveProfile($profileSaveRequest, $context);

        $this->checkoutContextService->refresh(
            $context->getTenantId(),
            $context->getTouchpoint()->getId(),
            $context->getToken()
        );

        return $this->redirectToRoute('account_profile', [
            'success' => true,
            'section' => 'profile',
        ]);
    }

    /**
     * @Route("/account/savePassword", name="account_save_password", methods={"POST"})
     */
    public function savePassword(PasswordSaveRequest $passwordSaveRequest, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo validate user input
        $this->accountService->savePassword($passwordSaveRequest, $context);
        $this->checkoutContextService->refresh($context->getTenantId(), $context->getTouchpoint()->getId(), $context->getToken());

        return $this->redirectToRoute('account_profile', [
            'success' => true,
            'section' => 'password',
        ]);
    }

    /**
     * @Route("/account/saveEmail", name="account_save_email", methods={"POST"})
     */
    public function saveEmail(EmailSaveRequest $emailSaveRequest, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo validate user input
        $this->accountService->saveEmail($emailSaveRequest, $context);
        $this->checkoutContextService->refresh($context->getTenantId(), $context->getTouchpoint()->getId(), $context->getToken());

        return $this->redirectToRoute('account_profile', [
            'success' => true,
            'section' => 'email',
        ]);
    }

    /**
     * @Route("/account/address", name="address_index", options={"seo"="false"})
     * @Method({"GET"})
     */
    public function addressOverview(CheckoutContext $context)
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('@Storefront/frontend/address/index.html.twig', [
            'customerAdressPage' => $this->customerAddressPageLoader->load($context),
        ]);
    }

    /**
     * @Route("/account/address/create", name="address_create", options={"seo"="false"})
     */
    public function createAddress(CheckoutContext $context): Response
    {
        return $this->renderStorefront('@Storefront/frontend/address/create.html.twig', [
            'countryList' => $this->accountService->getCountryList($context),
        ]);
    }

    /**
     * @Route("/account/address/save", name="address_save", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function saveAddress(AddressSaveRequest $request, Request $httpRequest, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->accountService->saveAddress($request, $context);

        if ($request->isDefaultBillingAddress()) {
            $this->accountService->setDefaultShippingAddress($request->getId(), $context);
        }
        if ($request->isDefaultShippingAddress()) {
            $this->accountService->setDefaultBillingAddress($request->getId(), $context);
        }

        $this->checkoutContextService->refresh($context->getTenantId(), $context->getTouchpoint()->getId(), $context->getToken());

        if ($url = $httpRequest->query->get('redirectTo')) {
            return $this->handleRedirectTo($url);
        }

        return $this->redirectToRoute('address_index');
    }

    /**
     * @Route("/account/address/edit", name="address_edit", options={"seo"="false"})
     */
    public function editAddress(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->query->get('addressId');
        $address = $this->accountService->getAddressById($addressId, $context);

        return $this->renderStorefront('@Storefront/frontend/address/edit.html.twig', [
            'formData' => $address,
            'countryList' => $this->accountService->getCountryList($context),
            'redirectTo' => $request->query->get('redirectTo'),
        ]);
    }

    /**
     * @Route("/account/address/delete_confirm", name="address_delete_confirm", options={"seo"="false"})
     * @Method({"GET"})
     */
    public function deleteAddressConfirm(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->query->get('addressId');
        $address = $this->accountService->getAddressById($addressId, $context);

        return $this->renderStorefront('@Storefront/frontend/address/delete.html.twig', ['address' => $address]);
    }

    /**
     * @Route("/account/address/delete", name="address_delete", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function deleteAddress(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->request->get('addressId');
        $this->accountService->deleteAddress($addressId, $context);

        return $this->redirectToRoute('address_index', ['success' => 'delete']);
    }

    /**
     * @Route("/account/address/setDefaultBillingAddress", name="address_set_default_billing", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function setDefaultBillingAddress(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->request->get('addressId');
        $this->accountService->setDefaultBillingAddress($addressId, $context);
        $this->checkoutContextService->refresh($context->getTenantId(), $context->getTouchpoint()->getId(), $context->getToken());

        return $this->redirectToRoute('address_index', ['success' => 'default_billing']);
    }

    /**
     * @Route("/account/address/setDefaultShippingAddress", name="address_set_default_shipping", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function setDefaultShippingAddress(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->request->get('addressId');
        $this->accountService->setDefaultShippingAddress($addressId, $context);
        $this->checkoutContextService->refresh($context->getTenantId(), $context->getTouchpoint()->getId(), $context->getToken());

        return $this->redirectToRoute('address_index', ['success' => 'default_shipping']);
    }

    /**
     * @Route("/account/address/ajaxSelection", name="address_ajax_selection", options={"seo"="false"})
     * @Method({"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function addressAjaxSelection(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->get('addressId');
        $setDefaultShippingAddress = (bool) $request->get('setDefaultShippingAddress', false);
        $setDefaultBillingAddress = (bool) $request->get('setDefaultBillingAddress', false);
        $addresses = $this->accountService->getAddressesByCustomer($context);

        if (!empty($addressId)) {
            /** @var CustomerAddressStruct $address */
            foreach ($addresses as $key => $address) {
                if ($address->getId() === $addressId) {
                    unset($addresses[$key]);
                }
            }
        }

        return $this->renderStorefront('@Storefront/frontend/address/ajax_selection.html.twig', [
            'addresses' => $addresses,
            'activeAddressId' => $addressId,
            'setDefaultBillingAddress' => $setDefaultBillingAddress,
            'setDefaultShippingAddress' => $setDefaultShippingAddress,
        ]);
    }

    /**
     * @Route("/account/address/ajaxEditor", name="address_ajax_editor", options={"seo"="false"})
     * @Method("GET")
     */
    public function addressAjaxEdit(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->query->get('addressId', '');
        if ($addressId) {
            $address = $this->accountService->getAddressById($addressId, $context);
        }

        return $this->renderStorefront('@Storefront/frontend/address/ajax_editor.html.twig', [
            'formData' => $address ?? null,
            'countryList' => $this->accountService->getCountryList($context),
        ]);
    }

    /**
     * @Route("/account/address/ajaxSave", name="address_ajax_save", options={"seo"="false"})
     * @Method("POST")
     *
     * @throws CustomerNotLoggedInException
     */
    public function addressAjaxSave(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo validate user input
        $formData = $request->request->get('address');
        $addressId = $this->accountService->saveAddress($formData, $context);

        if ($request->request->get('setDefaultShippingAddress')) {
            $this->accountService->setDefaultShippingAddress($addressId, $context);
        }
        if ($request->request->get('setDefaultBillingAddress')) {
            $this->accountService->setDefaultBillingAddress($addressId, $context);
        }

        $this->checkoutContextService->refresh($context->getTenantId(), $context->getTouchpoint()->getId(), $context->getToken());

        return new JsonResponse([
            'success' => true,
            'errors' => [],
            'data' => [],
        ]);
    }
}
