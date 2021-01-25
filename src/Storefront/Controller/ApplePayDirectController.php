<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Exception;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Helper\ProfileHelper;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\ProductService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\ShippingMethodService;
use Kiener\MolliePayments\Service\ShopService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Profile;
use RuntimeException;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApplePayDirectController extends StorefrontController
{
    /** @var MollieApiClient */
    private $apiClient;

    /** @var CartService */
    private $cartService;

    /** @var CustomerService */
    private $customerService;

    /** @var ShopService */
    private $shopService;

    /** @var OrderService */
    private $orderService;

    /** @var PaymentHandler */
    private $paymentHandler;

    /** @var EntityRepositoryInterface */
    private $paymentMethodRepository;

    /** @var ProductService */
    private $productService;

    /** @var RouterInterface */
    private $router;

    /** @var SalesChannelContextFactory */
    private $salesChannelContextFactory;

    /** @var SettingsService */
    private $settingsService;

    /** @var ShippingMethodService */
    private $shippingMethodService;

    public function __construct(
        MollieApiClient $apiClient,
        CartService $cartService,
        CustomerService $customerService,
        ShopService $shopService,
        OrderService $orderService,
        PaymentHandler $paymentHandler,
        EntityRepositoryInterface $paymentMethodRepository,
        ProductService $productService,
        Router $router,
        SalesChannelContextFactory $salesChannelContextFactory,
        SettingsService $settingsService,
        ShippingMethodService $shippingMethodService
    )
    {
        $this->apiClient = $apiClient;
        $this->cartService = $cartService;
        $this->customerService = $customerService;
        $this->shopService = $shopService;
        $this->orderService = $orderService;
        $this->paymentHandler = $paymentHandler;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->productService = $productService;
        $this->router = $router;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->settingsService = $settingsService;
        $this->shippingMethodService = $shippingMethodService;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/available", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.available",
     *                                       options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     *
     * @return JsonResponse
     */
    public function available(SalesChannelContext $context): JsonResponse
    {
        $available = false;

        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $context->getSalesChannel()->getId(),
            $context->getContext()
        );

        /** @var array|null $paymentMethodIds */
        $paymentMethodIds = $context->getSalesChannel()->getPaymentMethodIds();

        if (is_array($paymentMethodIds) && !empty($paymentMethodIds) && $settings->isEnableApplePayDirect()) {
            $applePayMethodId = $this->getPaymentMethodId(ApplePayPayment::class, $context->getContext());

            if ((string) $applePayMethodId !== '') {
                foreach ($paymentMethodIds as $paymentMethodId) {
                    if ($paymentMethodId === $applePayMethodId) {
                        $available = true;
                    }
                }
            }
        }

        return new JsonResponse([
            'available' => $available
        ]);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/session/{validationUrl}", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.session",
     *                                                     options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string              $validationUrl
     *
     * @return JsonResponse
     */
    public function applePaySession(SalesChannelContext $context, string $validationUrl): JsonResponse
    {
        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $context->getSalesChannel()->getId(),
            $context->getContext()
        );

        /** @var Profile|null $profile */
        $profile = ProfileHelper::getProfile(
            $this->apiClient,
            $settings
        );

        /** @var string|false $session */
        $session = false;

        if (
            $profile !== null
            && isset($profile->id)
        ) {
            $this->apiClient->wallets->requestApplePayPaymentSession(
                $this->shopService->getShopUrl(),
                $validationUrl
            );
        }

        return new JsonResponse([
            'session' => $session
        ]);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/product/{productId}", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.product",
     *                                                 options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     *
     * @param string              $productId
     *
     * @return JsonResponse
     */
    public function product(SalesChannelContext $context, string $productId): JsonResponse
    {
        /** @var ProductEntity $product */
        $product = $this->productService->getProductById($productId);

        /** @var array $returnData */
        $returnData = [
            'success' => false,
        ];

        // If the product is not null, add it to the return data
        if ($product !== null) {
            $returnData = [
                'success' => true,
                'data' => $product,
            ];
        }

        return new JsonResponse($returnData);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/product/{productId}/price", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.product.price",
     *                                                       options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     *
     * @param string              $productId
     *
     * @return JsonResponse
     */
    public function productPrice(SalesChannelContext $context, string $productId): JsonResponse
    {
        /** @var Price $price */
        $price = null;

        /** @var ProductEntity $product */
        $product = $this->productService->getProductById($productId);

        /** @var array $returnData */
        $returnData = [
            'success' => false,
        ];

        // If the product is not null, add it to the return data
        if ($product !== null) {
            $price = $product->getCurrencyPrice($context->getSalesChannel()->getCurrencyId());
        }

        if ($price !== null) {
            $returnData = [
                'success' => true,
                'data' => [
                    'id' => $product->getId(),
                    'price' => $price->getGross(),
                ],
            ];
        }

        return new JsonResponse($returnData);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/validate", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.validate",
     *                                      options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     *
     * @param Request             $request
     *
     * @return JsonResponse
     * @throws ApiException
     */
    public function validate(SalesChannelContext $context, Request $request): JsonResponse
    {
        /** @var false|string $paymentSession */
        $paymentSession = null;

        // We override the mode with live, as the validation can only be done in live mode
        $this->setApiKeysBySalesChannelContext($context, 'live');

        try {
            $paymentSession = $this->apiClient->wallets->requestApplePayPaymentSession(
                $this->shopService->getShopUrl(true),
                $request->get('validationUrl')
            );
        } catch (ApiException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'validationUrl' => $request->get('validationUrl')
            ]);
        }

        return new JsonResponse(json_decode($paymentSession, true));
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/authorize", defaults={"XmlHttpRequest"=true},
     *                                       name="frontend.mollie.apple-pay.authorize", options={"seo"="false"},
     *                                       methods={"POST"})
     *
     * @param SalesChannelContext $context
     *
     * @param Request             $request
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function authorize(SalesChannelContext $context, Request $request): JsonResponse
    {
        /** @var string|null $cartToken */
        $cartToken = $request->get('cartToken');

        /** @var CustomerEntity|null $customer */
        $customer = null;

        /** @var array $errors */
        $errors = [];

        /** @var OrderEntity $order */
        $order = null;

        /** @var string|null $returnUrl */
        $returnUrl = null;

        /** @var string|null $shippingMethodId */
        $shippingMethodId = $request->get('shippingMethodId');

        /** @var OrderTransactionEntity|null $transaction */
        $transaction = null;

        /** @var array $shippingContact */
        $shippingContact = json_decode($request->get('shippingContact'), true);

        // Check if the cart token is set
        if ((string) $cartToken === '') {
            $errors[] = 'The cart token for Apple Pay is unavailable.';
        }

        // Check if the shipping contact is set
        if (!is_array($shippingContact) || empty($shippingContact)) {
            $errors[] = 'The shipping contact information is unavailable.';
        }

        // Create the customer
        if (is_array($shippingContact) && !empty($shippingContact)) {
            try {
                $customer = $this->customerService->createCustomerForApplePayDirect(
                    $shippingContact,
                    $this->getPaymentMethodId(ApplePayPayment::class, $context->getContext()),
                    $context
                );
            } catch (Exception $e) {
                //
            }
        }

        // Check if the customer is created
        if ($customer === null || $customer === false) {
            $errors[] = 'The customer could not be created.';
        }

        // Convert the cart to an order
        if (
            $customer !== null
            && (string) $cartToken !== ''
        ) {
            try {
                $order = $this->process(
                    $customer,
                    $cartToken,
                    (string) $shippingMethodId,
                    $context
                );
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Get the return URL for the order
        if ($order !== null) {
            $returnUrl = $this->router->generate(
                'frontend.checkout.finish.page', [
                'orderId' => $order->getId(),
            ],
                $this->router::ABSOLUTE_URL
            );
        }

        // Get the transaction from the created order
        if ($order !== null) {
            /** @var OrderTransactionCollection|null $transactions */
            $transactions = $order->getTransactions();

            if (
                $transactions !== null
                && $transactions->count()
                && $transactions->last() !== null
            ) {
                $transaction = $transactions->last();
            }
        }

        // Create the order at Mollie
        if (
            $order !== null
            && $transaction !== null
        ) {
            try {
                $this->createOrderAtMollie(
                    $request->get('paymentToken'),
                    $order,
                    $returnUrl,
                    $transaction,
                    $context
                );
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($order !== null) {
            $errors[] = sprintf('No transaction for order %s', $order->getOrderNumber());
        }

        return new JsonResponse([
            'redirectUrl' => $returnUrl,
            'errors' => $errors,
        ]);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/shipping-methods", defaults={"XmlHttpRequest"=true}, name="frontend.mollie.apple-pay.shipping-methods",
     *                                              options={"seo"="false"}, methods={"POST"})
     *
     * @param SalesChannelContext $context
     *
     * @return JsonResponse
     */
    public function shippingMethods(SalesChannelContext $context, Request $request): JsonResponse
    {
        /** @var array $shippingMethods */
        $shippingMethods = [];

        /** @var string $countryCode */
        $countryCode = $request->get('countryCode');

        // Create a new sales channel context
        if ((string) $countryCode !== '') {
            $newSalesChannelContext = $this->createSalesChannelContext(
                Uuid::randomHex(),
                $context,
                $this->customerService->getCountryId($countryCode, $context->getContext()),
                null,
                null,
                null
            );
        } else {
            $newSalesChannelContext = $context;
        }

        try {
            $shippingMethods = $this->shippingMethodService->getShippingMethodsForApplePayDirect($newSalesChannelContext);
        } catch (Exception $e) {
            $shippingMethods['error'] = $e->getMessage();
        }

        return new JsonResponse($shippingMethods);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/apple-pay/shipping-costs/{shippingMethodId}/{productId}", defaults={"csrf_protected"=true}, name="frontend.mollie.apple-pay.shipping-costs",
     *                                                                           options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string              $shippingMethodId
     * @param string              $productId
     *
     * @return JsonResponse
     */
    public function shippingCosts(SalesChannelContext $context, $shippingMethodId, $productId): JsonResponse
    {
        /** @var Cart|null $cart */
        $cart = null;

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $this->getPaymentMethod(ApplePayPayment::class, $context->getContext());

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $this->shippingMethodService->getShippingMethodById($shippingMethodId, $context);

        // Create a new cart for the given product
        if (
            $paymentMethod !== null
            && $shippingMethod !== null
        ) {
            $cart = $this->cartService->createCartForProduct(
                $productId,
                $paymentMethod,
                $shippingMethod,
                $context
            );
        }

        return new JsonResponse([
            'totalPrice' => $cart !== null ? $cart->getDeliveries()->getShippingCosts()->sum()->getTotalPrice() : null,
            'shippingMethod' => $shippingMethod,
            'cartToken' => $cart !== null ? $cart->getToken() : null,
        ]);
    }

    /**
     *
     * @param CustomerEntity      $customer
     * @param string              $cartToken
     *
     * @param string              $shippingMethodId
     * @param SalesChannelContext $context
     *
     * @return OrderEntity|null
     * @throws Exception
     */
    public function process(CustomerEntity $customer, string $cartToken, string $shippingMethodId, SalesChannelContext $context): ?OrderEntity
    {
        /** @var Cart $cart */
        $cart = $this->cartService->getCart($cartToken, $context);

        // Handle errors
        if (
            $cart->getErrors()->count()
            && $cart->getErrors()->first() !== null
        ) {
            throw new Exception($cart->getErrors()->first()->getMessage());
        }

        /** @var OrderEntity $order */
        $order = null;

        /** @var string $orderId */
        $orderId = null;

        if ($cart !== null) {
            // Login the customer
            $this->customerService->customerLogin($customer, $context);

            // Create a new sales channel context
            $newSalesChannelContext = $this->createSalesChannelContext(
                Uuid::randomHex(),
                $context,
                $customer->getDefaultShippingAddress() !== null ? $customer->getDefaultShippingAddress()->getCountryId() : null,
                $customer->getId(),
                $customer->getDefaultPaymentMethod() !== null ? $customer->getDefaultPaymentMethod()->getId() : null,
                $shippingMethodId
            );

            // Persist the order
            $orderId = $this->cartService->order($cart, $newSalesChannelContext);
        }

        // Get the order from the repository
        if ($orderId !== null) {
            $order = $this->orderService->getOrder($orderId, $context->getContext());
        }

        return $order;
    }

    /**
     * Returns an order that is created through the Mollie API.
     *
     * @param string                 $applePaymentToken
     * @param OrderEntity            $order
     * @param string                 $returnUrl
     * @param OrderTransactionEntity $transaction
     *
     * @param SalesChannelContext    $salesChannelContext
     *
     * @return Order|null
     * @throws RuntimeException
     */
    private function createOrderAtMollie(
        string $applePaymentToken,
        OrderEntity $order,
        string $returnUrl,
        OrderTransactionEntity $transaction,
        SalesChannelContext $salesChannelContext
    ): ?Order
    {
        /** @var Order $mollieOrder */
        $mollieOrder = null;

        /** @var array $orderData */
        $orderData = $this->paymentHandler->prepareOrderForMollie(
            ApplePayPayment::PAYMENT_METHOD_NAME,
            $transaction->getId(),
            $order,
            (string) $returnUrl,
            $salesChannelContext,
            [
                'applePayPaymentToken' => $applePaymentToken,
            ]
        );

        // Create the order at Mollie
        if (
            is_array($orderData)
            && !empty($orderData)
        ) {
            $mollieOrder = $this->paymentHandler->createOrderAtMollie(
                $orderData,
                (string)$returnUrl,
                $order,
                $salesChannelContext
            );
        }

        return $mollieOrder;
    }

    /**
     * Returns a payment method by it's handler.
     *
     * @param string       $handlerIdentifier
     * @param Context|null $context
     *
     * @return PaymentMethodEntity|null
     */
    private function getPaymentMethod(string $handlerIdentifier, Context $context = null): ?PaymentMethodEntity
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));

            // Get payment methods
            $paymentMethods = $this->paymentMethodRepository->search($criteria, $context ?? Context::createDefaultContext());

            if (
                $paymentMethods !== null
                && $paymentMethods->count()
                && $paymentMethods->first() !== null
            ) {
                return $paymentMethods->first();
            }
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Returns a payment method id by it's handler.
     *
     * @param string       $handlerIdentifier
     * @param Context|null $context
     *
     * @return array|mixed|string|null
     */
    private function getPaymentMethodId(string $handlerIdentifier, Context $context = null)
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));

            // Get payment methods
            $paymentMethods = $this->paymentMethodRepository->searchIds($criteria, $context ?? Context::createDefaultContext())->getIds();
            return !empty($paymentMethods) ? $paymentMethods[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Since the guest customer was logged in, the context changed in the system,
     * but this doesn't effect the context given as parameter.
     * Because of that, a new context for the following operations is created
     *
     * @param string              $newToken
     * @param SalesChannelContext $context
     *
     * @param string|null         $countryId
     * @param string              $customerId
     * @param string              $paymentMethodId
     * @param string|null         $shippingMethodId
     *
     * @return SalesChannelContext
     */
    private function createSalesChannelContext(
        string $newToken,
        SalesChannelContext $context,
        ?string $countryId,
        ?string $customerId,
        ?string $paymentMethodId = null,
        ?string $shippingMethodId = null
    ): SalesChannelContext
    {
        /** @var array $options */
        $options = [];

        // Add country to options
        if ((string) $countryId !== '') {
            $options[SalesChannelContextService::COUNTRY_ID] = $countryId;
        }

        // Add customer to options
        if ((string) $customerId !== '') {
            $options[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        // Add payment method to options
        if ((string) $paymentMethodId !== '') {
            $options[SalesChannelContextService::PAYMENT_METHOD_ID] = $paymentMethodId;
        }

        // Add shipping method to options
        if ((string) $shippingMethodId !== '') {
            $options[SalesChannelContextService::SHIPPING_METHOD_ID] = $shippingMethodId;
        }

        $salesChannelContext = $this->salesChannelContextFactory->create(
            $newToken,
            $context->getSalesChannel()->getId(),
            $options
        );

        // todo: load matching rules
        $salesChannelContext->setRuleIds($context->getRuleIds());

        return $salesChannelContext;
    }

    /**
     * Sets the API keys for Mollie based on the current context.
     *
     * @param SalesChannelContext $context
     *
     * @param string|null         $mode
     *
     * @throws ApiException
     */
    private function setApiKeysBySalesChannelContext(SalesChannelContext $context, ?string $mode = null): void
    {
        try {
            /** @var MollieSettingStruct $settings */
            $settings = $this->settingsService->getSettings($context->getSalesChannel()->getId());

            /** @var string $apiKey */
            $apiKey = $settings->isTestMode() === false ? $settings->getLiveApiKey() : $settings->getTestApiKey();

            if ($mode === 'live') {
                $apiKey = $settings->getLiveApiKey();
            }

            if ($mode === 'test') {
                $apiKey = $settings->getTestApiKey();
            }

            // Set the API key
            $this->apiClient->setApiKey($apiKey);
        } catch (InconsistentCriteriaIdsException $e) {
            throw new RuntimeException(sprintf('Could not set Mollie Api Key, error: %s', $e->getMessage()));
        }
    }
}
