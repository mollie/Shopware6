<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilder;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Creates the Mollie session the express component is mounted with. A headless shop has no
 * storefront page the session could be attached to, so it asks for one here.
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class CreateSessionRoute extends AbstractCreateSessionRoute
{
    public const ORDER_ID_PARAMETER = 'orderId';

    /**
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: SessionBuilder::class)]
        private SessionBuilderInterface $sessionBuilder,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository
    ) {
    }

    public function getDecorated(): AbstractCreateSessionRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.express-components.create-session', path: '/store-api/mollie/express-components/create-session', methods: ['POST'])]
    public function createSession(Request $request, SalesChannelContext $salesChannelContext, ?Cart $cart = null, ?OrderEntity $order = null): CreateSessionResponse
    {
        $settings = $this->settingsService->getExpressComponentsSettings($salesChannelContext->getSalesChannelId());

        // where the component may be shown is left to the caller: the restrictions travel back in
        // the response, and a session is created for every position that asks for one
        $restrictions = $settings->getRestrictions();

        // a shop that never switched the feature on asks for a session on every page, so a
        // switched off component is an answer and not an error
        if ($settings->isEnabled() === false) {
            return new CreateSessionResponse(false, $restrictions);
        }

        $payableOrder = $this->resolveOrder($request, $salesChannelContext, $order);
        if ($payableOrder instanceof OrderEntity) {
            $session = $this->sessionBuilder->buildFromOrder($payableOrder, $salesChannelContext);

            return new CreateSessionResponse(true, $restrictions, $session->getId(), $session->getClientAccessToken());
        }

        // an empty cart has nothing to pay for, and Mollie rejects a session without lines
        if (! $cart instanceof Cart || $cart->getLineItems()->count() === 0) {
            return new CreateSessionResponse(true, $restrictions);
        }

        $session = $this->sessionBuilder->buildFromCart($cart, $salesChannelContext);

        return new CreateSessionResponse(true, $restrictions, $session->getId(), $session->getClientAccessToken());
    }

    /**
     * An order handed in by the storefront is taken as it is, the edit order page has already
     * checked who may see it.
     */
    private function resolveOrder(Request $request, SalesChannelContext $salesChannelContext, ?OrderEntity $order): ?OrderEntity
    {
        if ($order instanceof OrderEntity) {
            return $order;
        }

        $orderId = (string) $request->get(self::ORDER_ID_PARAMETER, '');
        if ($orderId === '') {
            return null;
        }

        return $this->loadOrder($orderId, $salesChannelContext);
    }

    /**
     * A caller who is not logged in gets the same answer as one asking for a foreign order, so
     * the route never tells whether that order exists.
     */
    private function loadOrder(string $orderId, SalesChannelContext $salesChannelContext): OrderEntity
    {
        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            throw ExpressComponentsException::orderNotFound($orderId);
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customer->getId()));
        $criteria->addFilter(new EqualsFilter('order.salesChannelId', $salesChannelContext->getSalesChannelId()));
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('currency');

        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();
        if (! $order instanceof OrderEntity) {
            throw ExpressComponentsException::orderNotFound($orderId);
        }

        return $order;
    }
}
