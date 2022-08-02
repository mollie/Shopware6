<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover;

use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemover;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SubscriptionRemover extends PaymentMethodRemover
{
    public const ALLOWED_METHODS = [
        'ideal',
        'bancontact',
        'sofort',
        'eps',
        'giropay',
        'belfius',
        'creditcard',
        'paypal',
    ];

    /**
     * @var OrderDataExtractor
     */
    private $orderDataExtractor;

    /**
     * @param ContainerInterface $container
     * @param RequestStack       $requestStack
     * @param OrderService       $orderService
     * @param SettingsService    $settingsService
     * @param OrderDataExtractor $orderDataExtractor
     * @param LoggerInterface    $logger
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, OrderDataExtractor $orderDataExtractor, LoggerInterface $logger)
    {
        parent::__construct($container, $requestStack, $orderService, $settingsService, $logger);

        $this->orderDataExtractor = $orderDataExtractor;
    }

    /**
     * @param PaymentMethodRouteResponse $originalData
     * @param SalesChannelContext        $context
     * @throws \Exception
     * @return PaymentMethodRouteResponse
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        if ($this->isCartRoute()) {
            $cart = $this->getCart($context);
            $isSubscription = $this->isSubscriptionCart($cart);
        }

        if ($this->isOrderRoute()) {
            $order = $this->getOrder($context->getContext());
            $isSubscription = $this->isSubscriptionOrder($order, $context->getContext());
        }

        if (!isset($isSubscription)) {
            $isSubscription = false;
        }

        if (!$isSubscription) {
            return $originalData;
        }

        foreach ($originalData->getPaymentMethods() as $key => $paymentMethod) {
            $attributes = new PaymentMethodAttributes($paymentMethod);

            $paymentMethodName = $attributes->getMollieIdentifier();

            if (!in_array($paymentMethodName, self::ALLOWED_METHODS)) {
                $originalData->getPaymentMethods()->remove($key);
            }
        }

        return $originalData;
    }

    /**
     * @param Cart $cart
     * @return bool
     */
    private function isSubscriptionCart(Cart $cart): bool
    {
        foreach ($cart->getLineItems() as $lineItem) {
            $attribute = new LineItemAttributes($lineItem);

            if ($attribute->isSubscriptionProduct()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param OrderEntity $order
     * @param Context     $context
     * @return bool
     */
    private function isSubscriptionOrder(OrderEntity $order, Context $context): bool
    {
        $lineItems = $this->orderDataExtractor->extractLineItems($order, $context);

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            $attributes = new OrderLineItemEntityAttributes($lineItem);

            if ($attributes->isSubscriptionProduct()) {
                return true;
            }
        }

        return false;
    }
}
