<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\ProductService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\ShippingMethodService;
use Kiener\MolliePayments\Service\ShopService;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PaypalExpressCheckoutController extends AbstractExpressCheckoutController
{
//
//    /** @var MollieApiClient */
//    private $apiClient;

    /** @var CartService */
    private $cartService;

    /** @var CustomerService */
    private $customerService;

    /** @var ShopService */
    private $shopService;

    /** @var OrderService */
    private $orderService;
//
//    /** @var PaymentHandler */
//    private $paymentHandler;
//
//    /** @var EntityRepositoryInterface */
//    private $paymentMethodRepository;

    /** @var ProductService */
    private $productService;

    /** @var RouterInterface */
    private $router;
//
//    /** @var SalesChannelContextFactory */
//    private $salesChannelContextFactory;
//
//    /** @var SettingsService */
//    private $settingsService;

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
//        $this->apiClient = $apiClient;
        $this->cartService = $cartService;
        $this->customerService = $customerService;
        $this->shopService = $shopService;
        $this->orderService = $orderService;
//        $this->paymentHandler = $paymentHandler;
//        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->productService = $productService;
        $this->router = $router;
//        $this->salesChannelContextFactory = $salesChannelContextFactory;
//        $this->settingsService = $settingsService;
        $this->shippingMethodService = $shippingMethodService;

        parent::__construct(
            $apiClient,
            $paymentHandler,
            $paymentMethodRepository,
            $salesChannelContextFactory,
            $settingsService
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
     * @param SalesChannelContext $context
     *
     * @return JsonResponse
     */
    public function express(Request $request, SalesChannelContext $context): JsonResponse
    {
        /** @var Cart $cart */
        $cart = null;

        /** @var CustomerEntity|null $customer */
        $customer = $context->getCustomer();

        /** @var array $errors */
        $errors = [];

        /** @var OrderEntity $order */
        $order = null;

        /** @var string|null $returnUrl */
        $returnUrl = null;

        /** @var OrderTransactionEntity|null $transaction */
        $transaction = null;

        /** @var string|null $productId */
        $productId = $request->get('productId');

        /** @var string|null $shippingMethodId */
        $shippingMethodId = $request->get('shippingMethodId');

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $this->getPaymentMethod(PayPalPayment::class, $context->getContext());

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

        // Create the customer if there's nobody logged in
        if (is_null($customer)) {
            try {
                $customer = $this->customerService->createCustomerFromData(
                    [

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

        return new JsonResponse(['customer' => $context->getCustomer()]);
    }
}
