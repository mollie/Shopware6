<?php

namespace Kiener\MolliePayments\Controller\Storefront\ApplePayDirect;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Repository\Order\OrderAddressRepository;
use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Traits\Storefront\RedirectTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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


/**
 * @RouteScope(scopes={"storefront"})
 */
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
     * @param OrderService $orderService
     * @param ApplePayPayment $paymentHandler
     * @param EntityRepositoryInterface $paymentMethodRepository
     * @param RouterInterface $router
     * @param MolliePaymentDoPay $molliePaymentDoPay
     * @param LoggerInterface $logger
     * @param CartBackupService $cartBackup
     * @param OrderAddressRepository $repoOrderAdresses
     * @param CompatibilityGatewayInterface $compatibilityGateway
     * @param FlashBag $sessionFlashBag
     */
    public function __construct(IsEnabledRoute $routeApplePayDirectEnabled, PaymentIdRoute $routeApplePayDirectId, AddProductRoute $routeApplePayAddProduct, CreateSessionRoute $routeApplePayCreateSession, ShippingMethodRoute $routeShippingMethod, ApplePayDirect $applePay, CartService $cartService, CustomerService $customerService, ShopService $shopService, OrderService $orderService, ApplePayPayment $paymentHandler, EntityRepositoryInterface $paymentMethodRepository, RouterInterface $router, SettingsService $settingsService, MolliePaymentDoPay $molliePaymentDoPay, MollieApiFactory $mollieApiFactory, LoggerInterface $logger, CartBackupService $cartBackup, OrderAddressRepository $repoOrderAdresses, CompatibilityGatewayInterface $compatibilityGateway, FlashBag $sessionFlashBag)
    {
        $this->applePay = $applePay;
        $this->cartService = $cartService;
        $this->customerService = $customerService;
        $this->orderService = $orderService;
        $this->paymentHandler = $paymentHandler;
        $this->repoPaymentMethods = $paymentMethodRepository;
        $this->router = $router;
        $this->molliePayments = $molliePaymentDoPay;
        $this->logger = $logger;
        $this->repoOrderAdresses = $repoOrderAdresses;
        $this->flashBag = $sessionFlashBag;
        $this->cartBackupService = $cartBackup;
    }


    /**
     * @Route("/mollie/apple-pay/available", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.available", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return JsonResponse
     */
    public function isPaymentAvailable(SalesChannelContext $context): JsonResponse
    {
        try {

            $isEnabled = $this->applePay->isApplePayDirectEnabled($context);

            return new JsonResponse([
                'available' => $isEnabled,
            ]);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct available: ' . $ex->getMessage());

            return new JsonResponse([
                'available' => false
            ]);
        }
    }

    /**
     * Gets the ID of the ApplePay payment method.
     * We need this in the storefront for some selectors in use cases like
     * hiding the payment method if its not available in the browser.
     *
     * ATTENTION:
     * this is not about Apple Pay Direct - but the namespace of the URL is a good one (/apple-pay)
     * and I don't want to create all kinds of new controllers
     * @Route("/mollie/apple-pay/applepay-id", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.id", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return JsonResponse
     */
    public function getApplePayID(SalesChannelContext $context): JsonResponse
    {
        try {

            $id = $this->applePay->getActiveApplePayID($context);

            return new JsonResponse([
                'id' => $id,
            ]);

        } catch (\Throwable $ex) {

            $this->logger->error('Apple Pay Direct ID: ' . $ex->getMessage());

            return new JsonResponse([
                'id' => 'not-found',
            ]);
        }
    }

    /**
     * @Route("/mollie/apple-pay/add-product", defaults={"csrf_protected"=false}, name="frontend.mollie.apple-pay.add-product", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @param Request $request
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

            $this->applePay->addProduct($productId, $quantity, $context);

            return new JsonResponse(['success' => true,]);
        } catch (\Throwable $ex) {
            $this->logger->error('Apple Pay Direct error when adding product: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
     * @Route("/mollie/apple-pay/validate", defaults={"csrf_protected"=false}, name="frontend.mollie.apple-pay.validate", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @param Request $request
     * @throws ApiException
     * @return JsonResponse
     * @return JsonResponse
     */
    public function createPaymentSession(SalesChannelContext $context, Request $request): JsonResponse
    {
        try {
            $content = json_decode($request->getContent(), true);

            $validationURL = (string)$content['validationUrl'];

            $session = $this->applePay->createPaymentSession($validationURL, $context);

            return new JsonResponse([
                'session' => $session,
            ]);
        } catch (\Throwable $ex) {
            $this->logger->error('Apple Pay Direct error when creating payment session: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
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

            $methods = $this->applePay->getShippingMethods($countryCode, $context);
            $formattedCart = $this->applePay->getCartFormatted($context);

            return new JsonResponse([
                'success' => true,
                'cart' => $formattedCart,
                'shippingmethods' => $methods,
            ]);
        } catch (\Throwable $ex) {
            $this->logger->error('Apple Pay Direct error when loading shipping methods: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
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

            $this->applePay->setShippingMethod($shippingMethodID, $context);

            $cart = $this->applePay->getCartFormatted($context);

            return new JsonResponse([
                'success' => true,
                'cart' => $cart,
            ]);
        } catch (\Throwable $ex) {
            $this->logger->error('Apple Pay Direct error when setting shipping method: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
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

                $this->applePay->prepareCustomer(
                    $firstname,
                    $lastname,
                    $email,
                    $street,
                    $zipcode,
                    $city,
                    $countryCode,
                    $paymentToken,
                    $context
                );


      ##         $customer = $this->customerService->createApplePayDirectCustomer(
      ##             $firstname,
      ##             $lastname,
      ##             $email,
      ##             '',
      ##             $street,
      ##             $zipcode,
      ##             $city,
      ##             $countryCode,
      ##             $applePayID,
      ##             $context
      ##         );

      ##         if (!$customer instanceof CustomerEntity) {
      ##             throw new \Exception('Error when creating customer!');
      ##         }

      ##         # now start the login of our customer.
      ##         # Our SalesChannelContext will be correctly updated after our
      ##         # forward to the finish-payment page.
      ##         $this->customerService->customerLogin($customer, $context);
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
     * @Route("/mollie/apple-pay/finish-payment", defaults={"XmlHttpRequest"=true, "csrf_protected"=false}, name="frontend.mollie.apple-pay.finish-payment", options={"seo"="false"}, methods={"GET"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @param Request $request
     * @return RedirectResponse
     */
    public function finishPayment(RequestDataBag $data, SalesChannelContext $context, Request $request): RedirectResponse
    {
        $firstname = $request->get('firstname', '');
        $lastname = $request->get('lastname', '');
        $street = $request->get('street', '');
        $zipcode = $request->get('postalCode', '');
        $city = $request->get('city', '');
        $countryCode = $request->get('countryCode', '');
        $paymentToken = $request->get('paymentToken', '');


        # ----------------------------------------------------------------------------
        # STEP 1: Create Order
        try {

            if (empty($paymentToken)) {
                throw new \Exception('PaymentToken not found!');
            }

            $order = $this->applePay->createOrder($context);

        } catch (Throwable $ex) {
            $this->logger->error('Apple Pay Direct error when finishing payment: ' . $ex->getMessage());

            # if we have an error here, we have to redirect to the confirm page
            $returnUrl = $this->getCheckoutConfirmPage($this->router);
            # also add an error for our target page
            $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));

            return new RedirectResponse($returnUrl);
        }


        # ----------------------------------------------------------------------------
        # STEP 2: Start Payment (CHECKPOINT: we have a valid shopware order now)
        try {

            $returnUrl = $this->applePay->createPayment(
                $order,
                $this->getCheckoutFinishPage($order->getId(), $this->router),
                $firstname,
                $lastname,
                $street,
                $zipcode,
                $city,
                $countryCode,
                $paymentToken,
                $context
            );

            # now also update the custom fields of our order
            # we want to have the mollie meta data in the
            # custom fields in Shopware too
            $this->orderService->updateMollieDataCustomFields($order, $paymentData->getMollieID(), $transaction->getId(), $context);


            return new RedirectResponse($returnUrl);

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
     * @Route("/mollie/apple-pay/restore-cart", defaults={"csrf_protected"=false}, name="frontend.mollie.apple-pay.restore-cart", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @return JsonResponse
     */
    public function restoreCart(SalesChannelContext $context): JsonResponse
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
     * @param null|Context $context
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
