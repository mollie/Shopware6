<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\ProductService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\ShippingMethodService;
use Kiener\MolliePayments\Service\ShopService;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class PaypalExpressCheckoutController extends AbstractExpressCheckoutController
{
    /** @var CartService */
    private $cartService;

    /** @var CustomerService */
    private $customerService;

    /** @var ShopService */
    private $shopService;

    /** @var OrderService */
    private $orderService;

    /** @var ProductService */
    private $productService;

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
        RouterInterface $router,
        SalesChannelContextFactory $salesChannelContextFactory,
        SettingsService $settingsService,
        ShippingMethodService $shippingMethodService,
        TokenFactoryInterfaceV2 $tokenFactory
    )
    {
        $this->cartService = $cartService;
        $this->customerService = $customerService;
        $this->shopService = $shopService;
        $this->orderService = $orderService;
        $this->productService = $productService;
        $this->shippingMethodService = $shippingMethodService;

        parent::__construct(
            $apiClient,
            $paymentHandler,
            $paymentMethodRepository,
            $router,
            $salesChannelContextFactory,
            $settingsService,
            $tokenFactory
        );
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/paypal-ecs/checkout",
     *     defaults={"XmlHttpRequest"=true},
     *     name="frontend.mollie.paypal-ecs.checkout",
     *     options={"seo"="false"},
     *     methods={"POST"}
     *     )
     *
     * @param Request $request
     * @param SalesChannelContext $context
     *
     * @return JsonResponse
     */
    public function express(Request $request, SalesChannelContext $context): JsonResponse
    {
        /** @var Cart $cart */
        $cart = null;

        /** @var string|null $shippingMethodId */
        $cartToken = $request->get('cartToken');

        /** @var CustomerEntity|null $customer */
        $customer = $context->getCustomer();

        /** @var array $errors */
        $errors = [];

        /** @var OrderEntity $order */
        $order = null;

        /** @var string|null $returnUrl */
        $returnUrl = null;

        /** @var string|null $checkoutUrl */
        $checkoutUrl = null;

        /** @var string|null $errorUrl */
        $errorUrl = $request->get('location');

        /** @var OrderTransactionEntity|null $transaction */
        $transaction = null;

        /** @var string|null $productId */
        $productId = $request->get('productId');

        /** @var string|null $countryCode */
        $countryCode = $request->get('countryCode');

        /** @var string|null $shippingMethodId */
        $shippingMethodId = $request->get('shippingMethodId');

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $this->getPaymentMethod(PayPalPayment::class, $context->getContext());

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $this->shippingMethodService->getShippingMethodById($shippingMethodId, $context);

        if (!is_null($cartToken)) {
            $cart = $this->cartService->getCart($cartToken, $context);
        } else if (
            $paymentMethod !== null
            && $shippingMethod !== null
            && $productId !== null
        ) {
            // Create a new cart for the given product
            $cart = $this->cartService->createCartForProduct(
                $productId,
                $paymentMethod,
                $shippingMethod,
                $context
            );
        }

        // Create the customer if there's nobody logged in
        if (is_null($customer)) {
            try {
                $customer = $this->customerService->createCustomerFromData(
                    [
                        'countryCode' => $countryCode,
                        'emailAddress' => 'place@holder.com',
                        'familyName' => 'placeholder', //lastname
                        'givenName' => 'placeholder', //firstname
                        'locality' => 'placeholder', //city
                        'postalCode' => '1234AB',
                        'addressLines' => [
                            'placeholder'
                        ],
                    ],
                    $paymentMethod,
                    $context
                );
            } catch (\Throwable $e) {
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
            && $cart !== null
        ) {
            try {
                $order = $this->process(
                    $customer,
                    $cart,
                    (string)$shippingMethodId,
                    (string)$paymentMethod->getId(),
                    $context
                );
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
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

        // Get the return URL for the order
        if ($order !== null) {
            $returnUrl = $this->createReturnUrlForOrder($order, $errorUrl);
        }

        // Create the order at Mollie
        if (
            $order !== null
            && $transaction !== null
        ) {
            try {
                $mollieOrder = $this->createOrderAtMollie(
                    PayPalPayment::PAYMENT_METHOD_NAME,
                    $order,
                    $returnUrl,
                    $transaction,
                    $context
                );

                // Get the payment url from the order at Mollie.
                if ($mollieOrder !== null) {
                    $checkoutUrl = isset($mollieOrder) ? $mollieOrder->getCheckoutUrl() : null;
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($order !== null) {
            $errors[] = sprintf('No transaction for order %s', $order->getOrderNumber());
        }

        return new JsonResponse([
            'checkoutUrl' => $checkoutUrl,
            'errors' => $errors
        ]);
    }


    /**
     *
     * @param CustomerEntity $customer
     * @param Cart $cart
     * @param string $shippingMethodId
     * @param SalesChannelContext $context
     *
     * @return OrderEntity|null
     * @throws \Exception
     */
    private function process(
        CustomerEntity $customer,
        Cart $cart,
        string $shippingMethodId,
        string $paymentMethodId,
        SalesChannelContext $context): ?OrderEntity
    {
        // Handle errors
        $errors = $cart->getErrors();

        if ($errors->count() > 0) {
            foreach ($errors as $error) {
                // if it is only a promotion added info notice, it is no error
                if ($error instanceof PromotionCartAddedInformationError) {
                    continue;
                }

                throw new \Exception($error->getMessage());
            }
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
                $customer->getUniqueIdentifier(),
                $paymentMethodId,
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
     * @param string $molliePaymentMethod
     * @param OrderEntity $order
     * @param string $returnUrl
     * @param OrderTransactionEntity $transaction
     *
     * @param SalesChannelContext $salesChannelContext
     * @param array $paymentData
     * @return Order|null
     */
    protected function createOrderAtMollie(
        string $molliePaymentMethod,
        OrderEntity $order,
        string $returnUrl,
        OrderTransactionEntity $transaction,
        SalesChannelContext $salesChannelContext,
        array $paymentData = []
    ): ?Order
    {
        /** @var Order $mollieOrder */
        $mollieOrder = null;

        /** @var array $orderData */
        $orderData = $this->paymentHandler->prepareOrderForMollie(
            $molliePaymentMethod,
            $transaction->getId(),
            $order,
            (string)$returnUrl,
            $salesChannelContext,
            $paymentData
        );

        unset($orderData[PaymentHandler::FIELD_SHIPPING_ADDRESS]);
        unset($orderData[PaymentHandler::FIELD_BILLING_ADDRESS]);

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

        $order = $this->orderService->getOrder($order->getUniqueIdentifier(), $salesChannelContext->getContext());

        $this->orderService->getOrderRepository()->update([[
            'id' => $order->getId(),
            'customFields' => array_merge_recursive($order->getCustomFields(), [
                CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS => [
                    PaymentHandler::EXPRESS_CHECKOUT => PayPalPayment::PAYMENT_METHOD_NAME,
                ]
            ])
        ]], $salesChannelContext->getContext());

        return $mollieOrder;
    }
}
