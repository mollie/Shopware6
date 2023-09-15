<?php

namespace Kiener\MolliePayments\Controller\Storefront\ApplePayDirect;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Repository\Customer\CustomerRepositoryInterface;
use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Traits\Storefront\RedirectTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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

class ApplePayDirectControllerBase extends StorefrontController
{
    use RedirectTrait;

    /**
     *
     */
    private const SNIPPET_ERROR = 'molliePayments.payments.applePayDirect.paymentError';

    private const FLOWBUILDER_SUCCESS = 'success';
    private const FLOWBUILDER_FAILED = 'failed';

    /**
     * @var ApplePayDirect
     */
    private $applePay;

    /**
     * @var CartBackupService
     */
    private $cartBackupService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ?FlashBag
     */
    private $flashBag;

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $flowBuilderDispatcher;

    /**
     * @var FlowBuilderEventFactory
     */
    private $flowBuilderEventFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $repoCustomers;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param ApplePayDirect $applePay
     * @param RouterInterface $router
     * @param LoggerInterface $logger
     * @param CartBackupService $cartBackup
     * @param null|FlashBag $sessionFlashBag
     * @param FlowBuilderFactory $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @param CustomerRepositoryInterface $repoCustomers
     * @param OrderService $orderService
     * @throws \Exception
     */
    public function __construct(ApplePayDirect $applePay, RouterInterface $router, LoggerInterface $logger, CartBackupService $cartBackup, ?FlashBag $sessionFlashBag, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, CustomerRepositoryInterface $repoCustomers, OrderService $orderService)
    {
        $this->applePay = $applePay;
        $this->router = $router;
        $this->logger = $logger;
        $this->flashBag = $sessionFlashBag;
        $this->cartBackupService = $cartBackup;
        $this->repoCustomers = $repoCustomers;
        $this->orderService = $orderService;

        $this->flowBuilderEventFactory = $flowBuilderEventFactory;
        $this->flowBuilderDispatcher = $flowBuilderFactory->createDispatcher();
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
     *
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
            # ! WE DO NOT LOG IN HERE!
            # otherwise we would always get logs if its just not enabled
            # so this is either a valid response or invalid one...that's it

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
        # we do only allow a few errors from within here
        # the rest should only be visible in the LOG files
        $allowErrorMessage = false;

        try {
            $content = json_decode((string)$request->getContent(), true);

            $productId = (isset($content['id'])) ? (string)$content['id'] : '';
            $quantity = (isset($content['quantity'])) ? (int)$content['quantity'] : 0;

            if (empty($productId)) {
                $allowErrorMessage = true;
                throw new \Exception('Please provide a product ID!');
            }

            if ($quantity <= 0) {
                $allowErrorMessage = true;
                throw new \Exception('Please provide a valid quantity > 0!');
            }

            $this->applePay->addProduct($productId, $quantity, $context);

            return new JsonResponse(['success' => true,]);
        } catch (\Throwable $ex) {
            $this->logger->error('Apple Pay Direct error when adding product: ' . $ex->getMessage());

            $viewJson = ['success' => false];

            if ($allowErrorMessage) {
                $viewJson['error'] = $ex->getMessage();
            }

            return new JsonResponse($viewJson, 500);
        }
    }

    /**
     * @Route("/mollie/apple-pay/validate", defaults={"csrf_protected"=false}, name="frontend.mollie.apple-pay.validate", options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @param Request $request
     * @return JsonResponse
     * @return JsonResponse
     */
    public function createPaymentSession(SalesChannelContext $context, Request $request): JsonResponse
    {
        try {
            $content = json_decode((string)$request->getContent(), true);

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
        $allowErrorMessage = false;

        try {
            $content = json_decode((string)$request->getContent(), true);

            $countryCode = (isset($content['countryCode'])) ? (string)$content['countryCode'] : '';

            if (empty($countryCode)) {
                $allowErrorMessage = true;
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

            $viewJson = ['success' => false];

            if ($allowErrorMessage) {
                $viewJson['error'] = $ex->getMessage();
            }

            return new JsonResponse($viewJson, 500);
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
        $allowErrorMessage = false;

        try {
            $json = json_decode((string)$request->getContent(), true);

            $shippingMethodID = (isset($json['identifier'])) ? (string)$json['identifier'] : '';

            if (empty($shippingMethodID)) {
                $allowErrorMessage = true;
                throw new \Exception('Please provide a Shipping Method identifier!');
            }

            $newContext = $this->applePay->setShippingMethod($shippingMethodID, $context);

            $cart = $this->applePay->getCartFormatted($newContext);

            return new JsonResponse([
                'success' => true,
                'cart' => $cart,
            ]);
        } catch (\Throwable $ex) {
            $this->logger->error('Apple Pay Direct error when setting shipping method: ' . $ex->getMessage());

            $viewJson = ['success' => false];

            if ($allowErrorMessage) {
                $viewJson['error'] = $ex->getMessage();
            }

            return new JsonResponse($viewJson, 500);
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


            $email = (string)$request->get('email', '');
            $firstname = (string)$request->get('firstname', '');
            $lastname = (string)$request->get('lastname', '');
            $street = (string)$request->get('street', '');
            $zipcode = (string)$request->get('postalCode', '');
            $city = (string)$request->get('city', '');
            $countryCode = (string)$request->get('countryCode', '');

            $paymentToken = (string)$request->get('paymentToken', '');

            if (empty($paymentToken)) {
                throw new \Exception('PaymentToken not found!');
            }


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


            # forward to the finish-payment page,
            # where our customer is correctly known, and where we
            # can continue with our correct sales channel context.
            return $this->forwardToRoute('frontend.mollie.apple-pay.finish-payment', []);
        } catch (\Throwable $ex) {
            $this->logger->error('Apple Pay Direct error when starting payment: ' . $ex->getMessage());

            # if we have an error here, we have to redirect to the confirm page
            $returnUrl = $this->getCheckoutConfirmPage($this->router);
            # also add an error for our target page
            if ($this->flashBag !== null) {
                $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));
            }

            return new RedirectResponse($returnUrl);
        }
    }

    /**
     * @Route("/mollie/apple-pay/finish-payment", defaults={"XmlHttpRequest"=true, "csrf_protected"=false}, name="frontend.mollie.apple-pay.finish-payment", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param Request $request
     * @throws \Exception
     * @return RedirectResponse
     */
    public function finishPayment(SalesChannelContext $context, Request $request): RedirectResponse
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
            if ($this->flashBag !== null) {
                $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));
            }

            return new RedirectResponse($returnUrl);
        }


        # ----------------------------------------------------------------------------
        # STEP 2: Start Payment (CHECKPOINT: we have a valid shopware order now)
        try {
            $returnUrl = $this->getCheckoutFinishPage($order->getId(), $this->router);

            $this->applePay->createPayment(
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

            # fire our custom storefront event
            $this->fireFlowBuilderStorefrontEvent(self::FLOWBUILDER_SUCCESS, $order, $context->getContext());

            return new RedirectResponse($returnUrl);
        } catch (Throwable $ex) {
            $this->logger->error('Apple Pay Direct error when finishing Mollie payment: ' . $ex->getMessage());

            # we already have a valid Order ID.
            # so we just need to make sure to edit that order
            $returnUrl = $this->getEditOrderPage($order->getId(), $this->router);

            # also add an error for our target page
            if ($this->flashBag !== null) {
                $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));
            }

            # fire our custom storefront event
            $this->fireFlowBuilderStorefrontEvent(self::FLOWBUILDER_FAILED, $order, $context->getContext());

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
            $this->applePay->restoreCart($context);

            return new JsonResponse(['success' => true,]);
        } catch (\Throwable $ex) {
            $this->logger->error('Apple Pay Direct restoring cart error: ' . $ex->getMessage());

            return new JsonResponse(['success' => false,], 500);
        }
    }

    /**
     * @param string $status
     * @param OrderEntity $order
     * @param Context $context
     * @throws \Exception
     * @return void
     */
    private function fireFlowBuilderStorefrontEvent(string $status, OrderEntity $order, Context $context): void
    {
        $orderCustomer = $order->getOrderCustomer();

        if (!$orderCustomer instanceof OrderCustomerEntity) {
            return;
        }

        $criteria = new Criteria([(string)$orderCustomer->getCustomerId()]);

        $customers = $this->repoCustomers->search($criteria, $context);

        if ($customers->count() <= 0) {
            return;
        }

        # we also have to reload the order because data is missing
        $finalOrder = $this->orderService->getOrder($order->getId(), $context);

        switch ($status) {
            case self::FLOWBUILDER_FAILED:
                $event = $this->flowBuilderEventFactory->buildOrderFailedEvent($customers->first(), $finalOrder, $context);
                break;

            default:
                $event = $this->flowBuilderEventFactory->buildOrderSuccessEvent($customers->first(), $finalOrder, $context);
        }

        $this->flowBuilderDispatcher->dispatch($event);
    }
}
