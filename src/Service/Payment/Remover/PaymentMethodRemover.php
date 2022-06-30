<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Kiener\MolliePayments\Exception\MissingRequestException;
use Kiener\MolliePayments\Exception\MissingRouteException;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class PaymentMethodRemover implements PaymentMethodRemoverInterface
{
    public const CART_ROUTES = [
        'frontend.checkout.cart.page',
        'frontend.checkout.confirm.page',
        'frontend.checkout.finish.page',
        'frontend.cart.offcanvas',
    ];

    public const ORDER_ROUTES = [
        'frontend.account.edit-order.page',
    ];

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
     * We have to use lazy loading for this. Otherwise, there are plugin compatibilities
     * with a circular reference...even though XML looks fine.
     *
     * @return CartService
     * @throws \Exception
     */
    protected function getCartServiceLazy(): CartService
    {
        $service = $this->container->get('Shopware\Core\Checkout\Cart\SalesChannel\CartService');

        if (!$service instanceof CartService) {
            throw new \Exception('CartService of Shopware not found!');
        }

        return $service;
    }

    /**
     * @return bool
     */
    protected function isAllowedRoute(): bool
    {
        try {
            $request = $this->requestStack->getCurrentRequest();

            if (!$request instanceof Request) {
                throw new MissingRequestException();
            }

            # we also need to allow removing for store-api calls
            # this is for the headless approach
            if (strpos($request->getPathInfo(), '/store-api') === 0) {
                return true;
            }

            $route = $this->getRouteFromRequest();

            return $this->isCartAwareRoute($route) || $this->isOrderAwareRoute($route);
        } catch (MissingRequestException | MissingRouteException $e) {

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
    protected function isCartAwareRoute(string $route = ""): bool
    {
        if(empty($route)) {
            $route = $this->getRouteFromRequest();
        }

        return in_array($route, self::CART_ROUTES);
    }

    /**
     * @param string $route
     * @return bool
     * @throws MissingRequestException
     * @throws MissingRouteException
     */
    protected function isOrderAwareRoute(string $route = ""): bool
    {
        if(empty($route)) {
            $route = $this->getRouteFromRequest();
        }

        return in_array($route, self::ORDER_ROUTES);
    }

    /**
     * @return string
     * @throws MissingRequestException
     * @throws MissingRouteException
     */
    private function getRouteFromRequest():string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            throw new MissingRequestException();
        }

        $route = $request->attributes->get('_route');

        if (!empty($route)) {
            return $route;
        }

        throw new MissingRouteException($request);
    }
}
