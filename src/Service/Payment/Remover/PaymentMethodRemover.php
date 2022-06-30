<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Kiener\MolliePayments\Exception\MissingCartServiceException;
use Kiener\MolliePayments\Exception\MissingRequestException;
use Kiener\MolliePayments\Exception\MissingRouteException;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
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
     * @var SettingsService
     */
    protected $settingsService;

    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ContainerInterface $container
     * @param SettingsService    $settingsService
     * @param OrderService       $orderService
     * @param RequestStack       $requestStack
     * @param LoggerInterface    $logger
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->orderService = $orderService;
        $this->settingsService = $settingsService;
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
     * @return bool
     * @throws MissingRequestException
     * @throws MissingRouteException
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
     * @return Cart
     * @throws MissingCartServiceException
     */
    public function getCart(SalesChannelContext $context): Cart
    {
        return $this->getCartServiceLazy()->getCart($context->getToken(), $context);
    }

    /**
     * We have to use lazy loading for this. Otherwise, there are plugin compatibilities
     * with a circular reference...even though XML looks fine.
     *
     * @return CartService
     * @throws MissingCartServiceException
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
     * @return bool
     * @throws MissingRequestException
     * @throws MissingRouteException
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
     * @throws BadRequestException
     * @throws MissingRequestException
     * @throws OrderNotFoundException
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
     * @return Request
     * @throws MissingRequestException
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
     * @return string
     * @throws MissingRequestException
     * @throws MissingRouteException
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
}
