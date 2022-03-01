<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Repository\Order\OrderAddressRepository;
use Kiener\MolliePayments\Service\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\DomainExtractor;
use Kiener\MolliePayments\Service\EventLogger;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\ShopService;
use Kiener\MolliePayments\Traits\Storefront\RedirectTrait;
use Mollie\Api\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class ApplePayDirectController extends StorefrontController
{

    use RedirectTrait;

    /**
     *
     */
    private const SNIPPET_ERROR = 'molliePayments.payments.applePayDirect.paymentError';

    /**
     * @var ApplePayDirect
     */
    private $applePay;

    /**
     * @var MollieApiFactory
     */
    private $mollieApiFactory;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CartBackupService
     */
    private $cartBackupService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var ShopService
     */
    private $shopService;

    /**
     * @var ApplePayPayment
     */
    private $paymentHandler;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var MolliePaymentDoPay
     */
    private $molliePayments;

    /**
     * @var OrderAddressRepository
     */
    private $repoOrderAdresses;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoPaymentMethods;

    /**
     * @var CompatibilityGatewayInterface
     */
    private $compatibilityGateway;

    /**
     * @var FlashBag
     */
    private $flashBag;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ApplePayDirect $applePay
     * @param CartService $cartService
     * @param CustomerService $customerService
     * @param ShopService $shopService
     * @param OrderService $orderService
     * @param ApplePayPayment $paymentHandler
     * @param EntityRepositoryInterface $paymentMethodRepository
     * @param RouterInterface $router
     * @param SettingsService $settingsService
     * @param MolliePaymentDoPay $molliePaymentDoPay
     * @param MollieApiFactory $mollieApiFactory
     * @param CartBackupService $cartBackup
     * @param OrderAddressRepository $repoOrderAdresses
     * @param $compatibilityGateway
     * @param FlashBag $sessionFlashBag
     */
    public function __construct(ApplePayDirect $applePay, CartService $cartService, CustomerService $customerService, ShopService $shopService, OrderService $orderService, ApplePayPayment $paymentHandler, EntityRepositoryInterface $paymentMethodRepository, RouterInterface $router, SettingsService $settingsService, MolliePaymentDoPay $molliePaymentDoPay, MollieApiFactory $mollieApiFactory, LoggerInterface $logger, CartBackupService $cartBackup, OrderAddressRepository $repoOrderAdresses, CompatibilityGatewayInterface $compatibilityGateway, FlashBag $sessionFlashBag)
    {
        $this->applePay = $applePay;
        $this->cartService = $cartService;
        $this->customerService = $customerService;
        $this->shopService = $shopService;
        $this->orderService = $orderService;
        $this->paymentHandler = $paymentHandler;
        $this->repoPaymentMethods = $paymentMethodRepository;
        $this->router = $router;
        $this->settingsService = $settingsService;
        $this->molliePayments = $molliePaymentDoPay;
        $this->mollieApiFactory = $mollieApiFactory;
        $this->logger = $logger;
        $this->repoOrderAdresses = $repoOrderAdresses;
        $this->flashBag = $sessionFlashBag;
        $this->cartBackupService = $cartBackup;
        $this->compatibilityGateway = $compatibilityGateway;
    }


    /**
     * Gets the ID of the ApplePay payment method.
     * We need this in the storefront for some selectors in use cases like
     * hiding the payment method if its not available in the browser.
     *
     * ATTENTION:
     * this is not about Apple Pay Direct - but the namespace of the URL is a good one (/apple-pay)
     * and I don't want to create all kinds of new controllers
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/applepay-id", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.id", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return JsonResponse
     */
    public function getApplePayID(SalesChannelContext $context): JsonResponse
    {
        try {

            $id = $this->getActiveApplePayID($context->getContext());

            return new JsonResponse([
                'id' => $id
            ]);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct ID: ' . $ex->getMessage());

            return new JsonResponse([
                'id' => 'not-found',
            ]);
        }
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/available", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.available", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return JsonResponse
     */
    public function isPaymentAvailable(SalesChannelContext $context): JsonResponse
    {
        try {
            $available = false;

            $settings = $this->settingsService->getSettings($this->compatibilityGateway->getSalesChannelID($context));

            /** @var array|null $salesChannelPaymentIDss */
            $salesChannelPaymentIDs = $context->getSalesChannel()->getPaymentMethodIds();

            if (is_array($salesChannelPaymentIDs) && $settings->isEnableApplePayDirect()) {

                $applePayMethodID = $this->getActiveApplePayID($context->getContext());

                foreach ($salesChannelPaymentIDs as $tempID) {
                    # verify if our apple pay payment method is indeed in use
                    # for the current sales channel
                    if ($tempID === $applePayMethodID) {
                        $available = true;
                        break;
                    }
                }
            }

            return new JsonResponse([
                'available' => $available
            ]);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct available: ' . $ex->getMessage());

            return new JsonResponse([
                'available' => false
            ]);
        }
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/add-product", defaults={"csrf_protected"=false}, name="frontend.mollie.apple-pay.add-product", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @param string $productId
     * @return JsonResponse
     */
    public function addProduct(SalesChannelContext $context, Request $request): JsonResponse
    {
        try {

            $content = json_decode($request->getContent(), true);

            $productId = (string)$content['id'];
            $quantity = (int)$content['quantity'];

            if (empty($productId)) {
                throw new \Exception('Please provide a product ID!');
            }

            if ($quantity <= 0) {
                throw new \Exception('Please provide a valid quantity > 0!');
            }

            # if we already have a backup cart, then do NOT backup again.
            # because this could backup our temp. apple pay cart
            if (!$this->cartBackupService->isBackupExisting($context)) {
                $this->cartBackupService->backupCart($context);
            }


            $cart = $this->cartService->getCalculatedMainCart($context);

            # clear existing cart and also update it to save it
            $cart->setLineItems(new LineItemCollection());
            $this->cartService->updateCart($cart);

            # add new product to cart
            $this->cartService->addProduct($productId, $quantity, $context);

            return new JsonResponse(['success' => true,]);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct error when adding product: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/validate", defaults={"csrf_protected"=false}, name="frontend.mollie.apple-pay.validate", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @param Request $request
     * @return JsonResponse
     * @throws ApiException
     */
    public function createPaymentSession(SalesChannelContext $context, Request $request): JsonResponse
    {
        try {

            $content = json_decode($request->getContent(), true);

            $validationURL = (string)$content['validationUrl'];

            # make sure to get rid off any http prefixes or
            # also any sub shop slugs like /de or anything else
            # that would NOT work with Mollie and Apple Pay!
            $domainExtractor = new DomainExtractor();
            $domain = $domainExtractor->getCleanDomain($this->shopService->getShopUrl(true));

            # we always have to use the LIVE api key for
            # our first domain validation for Apple Pay!
            # the rest will be done with our test API key (if test mode active), or also Live API key (no test mode)
            $liveClient = $this->mollieApiFactory->getLiveClient($this->compatibilityGateway->getSalesChannelID($context));

            $paymentSession = $liveClient->wallets->requestApplePayPaymentSession($domain, $validationURL);

            return new JsonResponse([
                'session' => $paymentSession,
            ]);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct error when creating payment session: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/shipping-methods", defaults={"XmlHttpRequest"=true, "csrf_protected"=false}, name="frontend.mollie.apple-pay.shipping-methods", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @param Request $request
     * @return JsonResponse
     */
    public function getShippingMethods(SalesChannelContext $context, Request $request): JsonResponse
    {
        try {

            $content = json_decode($request->getContent(), true);

            $countryCode = (string)$content['countryCode'];

            if (empty($countryCode)) {
                throw new \Exception('No Country Code provided!');
            }

            $currentMethodID = $context->getShippingMethod()->getId();

            $countryID = $this->customerService->getCountryId($countryCode, $context->getContext());

            # get all available shipping methods of
            # our current country for Apple Pay
            $shippingMethods = $this->applePay->getShippingMethods($countryID, $context);

            # restore our previously used shipping method
            $context = $this->cartService->updateShippingMethod($context, $currentMethodID);

            # ...and get our calculated cart
            $swCart = $this->cartService->getCalculatedMainCart($context);
            $applePayCart = $this->applePay->buildApplePayCart($swCart);

            return new JsonResponse([
                'success' => true,
                'cart' => $this->applePay->format($applePayCart, $this->isMollieTestMode($context), $context),
                'shippingmethods' => $shippingMethods,
            ]);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct error when loading shipping methods: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/set-shipping", defaults={"XmlHttpRequest"=true, "csrf_protected"=false}, name="frontend.mollie.apple-pay.set-shipping", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @param Request $request
     * @return JsonResponse
     */
    public function setShippingMethod(SalesChannelContext $context, Request $request): JsonResponse
    {
        try {

            $json = json_decode($request->getContent(), true);
            $shippingMethodID = (string)$json['identifier'];

            if (empty($shippingMethodID)) {
                throw new \Exception('Please provide a Shipping Method identifier!');
            }

            $context = $this->cartService->updateShippingMethod($context, $shippingMethodID);

            $swCart = $this->cartService->getCalculatedMainCart($context);
            $applePayCart = $this->applePay->buildApplePayCart($swCart);

            return new JsonResponse([
                'success' => true,
                'cart' => $this->applePay->format($applePayCart, $this->isMollieTestMode($context), $context)
            ]);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct error when setting shipping method: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/start-payment", defaults={"XmlHttpRequest"=true, "csrf_protected"=false}, name="frontend.mollie.apple-pay.start-payment", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @param Request $request
     * @return Response
     */
    public function startPayment(SalesChannelContext $context, Request $request): Response
    {
        try {

            # we clear our cart backup now
            # we are in the user redirection process where a restoring wouldnt make sense
            # because from now on we would end on the cart page where we could even switch payment method.
            $this->cartBackupService->clearBackup($context);


            $email = $request->get('email', '');
            $firstname = $request->get('firstname', '');
            $lastname = $request->get('lastname', '');
            $street = $request->get('street', '');
            $zipcode = $request->get('postalCode', '');
            $city = $request->get('city', '');
            $countryCode = $request->get('countryCode', '');
            $paymentToken = $request->get('paymentToken', '');

            if (empty($paymentToken)) {
                throw new \Exception('PaymentToken not found!');
            }

            $applePayID = $this->getActiveApplePayID($context->getContext());


            # if we are not logged in,
            # then we have to create a new guest customer for our express order
            if (!$this->customerService->isCustomerLoggedIn($context)) {

                $customer = $this->customerService->createApplePayDirectCustomer(
                    $firstname,
                    $lastname,
                    $email,
                    '',
                    $street,
                    $zipcode,
                    $city,
                    $countryCode,
                    $applePayID,
                    $context
                );

                if (!$customer instanceof CustomerEntity) {
                    throw new \Exception('Error when creating customer!');
                }

                # now start the login of our customer.
                # Our SalesChannelContext will be correctly updated after our
                # forward to the finish-payment page.
                $this->customerService->customerLogin($customer, $context);
            }

            # also update our payment method to use ApplePay
            $context = $this->cartService->updatePaymentMethod($context, $applePayID);

            # forward to the finish-payment page,
            # where our customer is correctly known, and where we
            # can continue with our correct sales channel context.
            return $this->forwardToRoute('frontend.mollie.apple-pay.finish-payment', []);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct error when starting payment: ' . $ex->getMessage());

            # if we have an error here, we have to redirect to the confirm page
            $returnUrl = $this->getCheckoutConfirmPage($this->router);
            # also add an error for our target page
            $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));

            return new RedirectResponse($returnUrl);
        }
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/finish-payment", defaults={"XmlHttpRequest"=true, "csrf_protected"=false}, name="frontend.mollie.apple-pay.finish-payment", options={"seo"="false"}, methods={"GET"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @param Request $request
     * @return RedirectResponse
     */
    public function finishPayment(RequestDataBag $data, SalesChannelContext $context, Request $request): RedirectResponse
    {
        /** @var OrderEntity $order */
        $order = null;


        try {

            $paymentToken = $request->get('paymentToken', '');

            if (empty($paymentToken)) {
                throw new \Exception('PaymentToken not found!');
            }

            $firstname = $request->get('firstname', '');
            $lastname = $request->get('lastname', '');
            $street = $request->get('street', '');
            $zipcode = $request->get('postalCode', '');
            $city = $request->get('city', '');
            $countryCode = $request->get('countryCode', '');


            # immediately try to get the country of the buyer.
            # maybe this could lead to an exception if that country is not possible.
            # that's why we do it within these first steps.
            $countryID = $this->customerService->getCountryId($countryCode, $context->getContext());


            # we have to agree to the terms of services
            # to avoid constraint violation checks
            $data->add(['tos' => true]);

            # create our new Order using the
            # Shopware function for it.
            $order = $this->orderService->createOrder($data, $context);


        } catch (Throwable $ex) {

            $this->logger->error('Apple Pay Direct error when finishing payment: ' . $ex->getMessage());

            # if we have an error here, we have to redirect to the confirm page
            $returnUrl = $this->getCheckoutConfirmPage($this->router);
            # also add an error for our target page
            $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));

            return new RedirectResponse($returnUrl);
        }

        # ----------------------------------------------------------------------------
        # CHECKPOINT
        # we have a valid shopware order now

        try {

            # always make sure to use the correct address from Apple Pay
            # and never the one from the customer (if already existing)
            foreach ($order->getAddresses() as $address) {
                $this->repoOrderAdresses->updateAddress(
                    $address->getId(),
                    $firstname,
                    $lastname,
                    $street,
                    $zipcode,
                    $city,
                    $countryID,
                    $context->getContext()
                );
            }


            # get the latest new transaction.
            # we need this for our payment handler
            /** @var OrderTransactionCollection $transactions */
            $transactions = $order->getTransactions();
            $transaction = $transactions->last();

            if (!$transaction instanceof OrderTransactionEntity) {
                throw new \Exception('Created Apple Pay Direct order has not OrderTransaction!');
            }

            # generate the finish URL for our shopware page.
            # This is required, because we will immediately bring the user to this page.
            $shopwareReturnUrl = $this->getCheckoutFinishPage($order->getId(), $this->router);
            $asyncPaymentTransition = new AsyncPaymentTransactionStruct($transaction, $order, $shopwareReturnUrl);

            # now set the Apple Pay payment token for our payment handler.
            # This is required for a smooth checkout with our already validated Apple Pay transaction.
            $this->paymentHandler->setToken($paymentToken);

            $paymentData = $this->molliePayments->startMolliePayment(ApplePayPayment::PAYMENT_METHOD_NAME, $asyncPaymentTransition, $context, $this->paymentHandler);

            if (empty($paymentData->getCheckoutURL())) {
                throw new \Exception('Error when creating Apple Pay order in Mollie');
            }


            # now also update the custom fields of our order
            # we want to have the mollie meta data in the
            # custom fields in Shopware too
            $this->orderService->updateMollieDataCustomFields($order, $paymentData->getMollieID(), $transaction->getId(), $context);


            return new RedirectResponse($shopwareReturnUrl);

        } catch (Throwable $ex) {

            $this->logger->error('Apple Pay Direct error when finishing Mollie payment: ' . $ex->getMessage());

            # we already have a valid Order ID.
            # so we just need to make sure to edit that order
            $returnUrl = $this->getEditOrderPage($order->getId(), $this->router);
            # also add an error for our target page
            $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));

            return new RedirectResponse($returnUrl);
        }
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/restore-cart", defaults={"csrf_protected"=false}, name="frontend.mollie.apple-pay.restore-cart", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @param string $productId
     * @return JsonResponse
     */
    public function restoreCart(SalesChannelContext $context, Request $request): JsonResponse
    {
        try {

            if ($this->cartBackupService->isBackupExisting($context)) {
                $this->cartBackupService->restoreCart($context);
            }

            $this->cartBackupService->clearBackup($context);

            return new JsonResponse(['success' => true,]);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct restoring cart error: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
     * @param SalesChannelContext $context
     * @return bool
     */
    private function isMollieTestMode(SalesChannelContext $context): bool
    {
        $scID = $this->compatibilityGateway->getSalesChannelID($context);

        return $this->settingsService->getSettings($scID)->isTestMode();
    }

    /**
     * @param Context|null $context
     * @return array|string
     */
    private function getActiveApplePayID(Context $context = null)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', ApplePayPayment::class));
        $criteria->addFilter(new EqualsFilter('active', true));

        // Get payment methods
        $paymentMethods = $this->repoPaymentMethods->searchIds($criteria, $context ?? Context::createDefaultContext())->getIds();

        return $paymentMethods[0];
    }

}
