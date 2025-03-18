<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Kiener\MolliePayments\Exception\MissingCartServiceException;
use Kiener\MolliePayments\Exception\MissingRequestException;
use Kiener\MolliePayments\Exception\MissingRouteException;
use Kiener\MolliePayments\Service\MollieApi\OrderItemsExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class PaymentMethodRemover implements PaymentMethodRemoverInterface, CartAwareRouteInterface, OrderAwareRouteInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * @var SettingsService
     */
    protected $settingsService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderItemsExtractor
     */
    private $orderDataExtractor;

    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, OrderItemsExtractor $orderDataExtractor, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->orderService = $orderService;
        $this->settingsService = $settingsService;
        $this->orderDataExtractor = $orderDataExtractor;
        $this->logger = $logger;
    }

    /**
     * @throws MissingRequestException
     * @throws MissingRouteException
     */
    public function isCartRoute(): bool
    {
        $route = $this->getRouteFromRequest();

        return in_array($route, CartAwareRouteInterface::CART_ROUTES);
    }

    /**
     * @throws MissingCartServiceException
     */
    public function getCart(SalesChannelContext $context): Cart
    {
        return $this->getCartServiceLazy()->getCart($context->getToken(), $context);
    }

    /**
     * @throws MissingRequestException
     * @throws MissingRouteException
     */
    public function isOrderRoute(): bool
    {
        $route = $this->getRouteFromRequest();

        return in_array($route, OrderAwareRouteInterface::ORDER_ROUTES);
    }

    public function getOrder(Context $context): OrderEntity
    {
        $request = $this->getRequestFromStack();
        $orderId = $request->attributes->get('orderId');

        if (! $orderId) {
            throw new BadRequestException();
        }

        return $this->orderService->getOrder($orderId, $context);
    }

    protected function isAllowedRoute(): bool
    {
        try {
            $request = $this->getRequestFromStack();

            // we also need to allow removing for store-api calls
            // this is for the headless approach
            if (strpos($request->getPathInfo(), '/store-api') === 0) {
                return true;
            }

            if ($this->isCartRoute()) {
                return true;
            }

            if ($this->isOrderRoute()) {
                return true;
            }
        } catch (MissingRequestException|MissingRouteException $e) {
            $this->logger
                ->error('Could not determine if the current route is allowed to remove payment methods', [
                    'exception' => $e,
                    'request' => $request ?? null,
                ])
            ;

            // Make sure Shopware will behave normally in the case of an error.
            return false;
        }

        return false;
    }

    /**
     * We have to use lazy loading for this. Otherwise, there are plugin compatibilities
     * with a circular reference...even though XML looks fine.
     *
     * @throws MissingCartServiceException
     */
    protected function getCartServiceLazy(): CartService
    {
        $service = $this->container->get('Shopware\Core\Checkout\Cart\SalesChannel\CartService');

        if (! $service instanceof CartService) {
            throw new MissingCartServiceException();
        }

        return $service;
    }

    protected function isSubscriptionCart(Cart $cart): bool
    {
        foreach ($cart->getLineItems() as $lineItem) {
            $attribute = new LineItemAttributes($lineItem);

            if ($attribute->isSubscriptionProduct()) {
                return true;
            }
        }

        return false;
    }

    protected function isSubscriptionOrder(OrderEntity $order, Context $context): bool
    {
        $lineItems = $this->orderDataExtractor->extractLineItems($order);

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            $attributes = new OrderLineItemEntityAttributes($lineItem);

            if ($attributes->isSubscriptionProduct()) {
                return true;
            }
        }

        return false;
    }

    protected function isVoucherOrder(OrderEntity $order, Context $context): bool
    {
        $lineItems = $this->orderDataExtractor->extractLineItems($order);

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            $attributes = new OrderLineItemEntityAttributes($lineItem);

            if (VoucherType::isVoucherProduct($attributes->getVoucherType())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws MissingRequestException
     */
    private function getRequestFromStack(): Request
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request instanceof Request) {
            return $request;
        }

        throw new MissingRequestException();
    }

    /**
     * @throws MissingRequestException
     * @throws MissingRouteException
     */
    private function getRouteFromRequest(): string
    {
        $request = $this->getRequestFromStack();

        $route = (string) $request->attributes->get('_route');

        if (! empty($route)) {
            return $route;
        }

        return '';
    }
}
