<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Kiener\MolliePayments\Exception\MissingCartServiceException;
use Kiener\MolliePayments\Exception\MissingRequestException;
use Kiener\MolliePayments\Exception\MissingRouteException;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
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
     * @var OrderDataExtractor
     */
    private $orderDataExtractor;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ContainerInterface $container
     * @param RequestStack $requestStack
     * @param OrderService $orderService
     * @param SettingsService $settingsService
     * @param OrderDataExtractor $orderDataExtractor
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, OrderDataExtractor $orderDataExtractor, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->orderService = $orderService;
        $this->settingsService = $settingsService;
        $this->orderDataExtractor = $orderDataExtractor;
        $this->logger = $logger;
    }


    /**
     * @return bool
     */
    protected function isAllowedRoute(): bool
    {
        try {
            $request = $this->getRequestFromStack();

            # we also need to allow removing for store-api calls
            # this is for the headless approach
            if (strpos($request->getPathInfo(), '/store-api') === 0) {
                return true;
            }

            $route = $this->getRouteFromRequest();

            return $this->isCartRoute($route) || $this->isOrderRoute($route);
        } catch (MissingRequestException|MissingRouteException $e) {
            $this->logger
                ->error('Could not determine if the current route is allowed to remove payment methods', [
                    'exception' => $e,
                    'request' => $request ?? null,
                ]);

            // Make sure Shopware will behave normally in the case of an error.
            return false;
        }
    }

    /**
     * @param string $route
     * @throws MissingRouteException
     * @throws MissingRequestException
     * @return bool
     */
    public function isCartRoute(string $route = ""): bool
    {
        if (empty($route)) {
            $route = $this->getRouteFromRequest();
        }

        return in_array($route, CartAwareRouteInterface::CART_ROUTES);
    }

    /**
     * @param SalesChannelContext $context
     * @throws MissingCartServiceException
     * @return Cart
     */
    public function getCart(SalesChannelContext $context): Cart
    {
        return $this->getCartServiceLazy()->getCart($context->getToken(), $context);
    }

    /**
     * We have to use lazy loading for this. Otherwise, there are plugin compatibilities
     * with a circular reference...even though XML looks fine.
     *
     * @throws MissingCartServiceException
     * @return CartService
     */
    protected function getCartServiceLazy(): CartService
    {
        $service = $this->container->get('Shopware\Core\Checkout\Cart\SalesChannel\CartService');

        if (!$service instanceof CartService) {
            throw new MissingCartServiceException();
        }

        return $service;
    }

    /**
     * @param string $route
     * @throws MissingRouteException
     * @throws MissingRequestException
     * @return bool
     */
    public function isOrderRoute(string $route = ""): bool
    {
        if (empty($route)) {
            $route = $this->getRouteFromRequest();
        }

        return in_array($route, OrderAwareRouteInterface::ORDER_ROUTES);
    }

    /**
     * @param Context $context
     * @return OrderEntity
     */
    public function getOrder(Context $context): OrderEntity
    {
        $request = $this->getRequestFromStack();
        $orderId = $request->attributes->get('orderId');

        if (!$orderId) {
            throw new BadRequestException();
        }

        return $this->orderService->getOrder($orderId, $context);
    }

    /**
     * @throws MissingRequestException
     * @return Request
     */
    protected function getRequestFromStack(): Request
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request instanceof Request) {
            return $request;
        }

        throw new MissingRequestException();
    }

    /**
     * @throws MissingRouteException
     * @throws MissingRequestException
     * @return string
     */
    protected function getRouteFromRequest(): string
    {
        $request = $this->getRequestFromStack();

        $route = $request->attributes->get('_route');

        if (!empty($route)) {
            return $route;
        }

        throw new MissingRouteException($request);
    }


    /**
     * @param Cart $cart
     * @return bool
     */
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

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return bool
     */
    protected function isSubscriptionOrder(OrderEntity $order, Context $context): bool
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

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return bool
     */
    protected function isVoucherOrder(OrderEntity $order, Context $context): bool
    {
        $lineItems = $this->orderDataExtractor->extractLineItems($order, $context);

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            $attributes = new OrderLineItemEntityAttributes($lineItem);

            if (VoucherType::isVoucherProduct($attributes->getVoucherType())) {
                return true;
            }
        }

        return false;
    }
}
