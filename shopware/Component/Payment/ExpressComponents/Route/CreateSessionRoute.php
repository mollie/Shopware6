<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilder;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

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
        private EntityRepository $orderRepository,
        #[Autowire(service: LineItemAnalyzer::class)]
        private LineItemAnalyzerInterface $lineItemAnalyzer,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
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

        $restrictions = $settings->getRestrictions();

        if ($settings->isEnabled() === false) {
            return new CreateSessionResponse(false, $restrictions);
        }

        $payableOrder = $this->resolveOrder($request, $salesChannelContext, $order);
        if ($payableOrder instanceof OrderEntity) {
            if ($this->isPaid($payableOrder)) {
                $this->logger->debug('Express components session not created, the order is already paid', [
                    'salesChannelId' => $salesChannelContext->getSalesChannelId(),
                    'orderId' => $payableOrder->getId(),
                ]);

                return new CreateSessionResponse(true, $restrictions);
            }

            $session = $this->sessionBuilder->buildFromOrder($payableOrder, $salesChannelContext);

            return new CreateSessionResponse(true, $restrictions, $session->getId(), $session->getClientAccessToken());
        }

        if (! $cart instanceof Cart || $cart->getLineItems()->count() === 0) {
            return new CreateSessionResponse(true, $restrictions);
        }

        $orderBlockingErrors = $cart->getErrors()->filter(function (Error $error): bool {
            return ! $error instanceof PaymentMethodBlockedError && ! $error instanceof ShippingMethodBlockedError;
        });

        if ($orderBlockingErrors->blockOrder()) {
            $this->logger->debug('Express components session not created, the cart has errors that block the order', [
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
                'errors' => $orderBlockingErrors->getKeys(),
            ]);

            return new CreateSessionResponse(true, $restrictions);
        }

        $customer = $salesChannelContext->getCustomer();
        $isRegisteredCustomer = $customer instanceof CustomerEntity && $customer->getGuest() === false;
        if ($isRegisteredCustomer === false && $this->hasSubscriptionProduct($cart, $salesChannelContext)) {
            $this->logger->debug('Express components session not created, subscription products require a registered customer', [
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            ]);

            return new CreateSessionResponse(true, $restrictions);
        }

        $session = $this->sessionBuilder->buildFromCart($cart, $salesChannelContext);

        return new CreateSessionResponse(true, $restrictions, $session->getId(), $session->getClientAccessToken());
    }

    private function hasSubscriptionProduct(Cart $cart, SalesChannelContext $salesChannelContext): bool
    {
        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelContext->getSalesChannelId());
        if ($subscriptionSettings->isEnabled() === false) {
            return false;
        }

        $lineItems = new LineItemCollection($cart->getLineItems()->getFlat());

        return $this->lineItemAnalyzer->hasSubscriptionProduct($lineItems);
    }

    private function isPaid(OrderEntity $order): bool
    {
        $transactions = $order->getTransactions();
        if (! $transactions instanceof OrderTransactionCollection) {
            return false;
        }

        $paidTransactions = $transactions->filter(function (OrderTransactionEntity $transaction): bool {
            $state = $transaction->getStateMachineState();
            if (! $state instanceof StateMachineStateEntity) {
                return false;
            }

            return $state->getTechnicalName() === OrderTransactionStates::STATE_PAID;
        });

        return $paidTransactions->count() > 0;
    }

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
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('currency');

        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->getEntities()->first();
        if (! $order instanceof OrderEntity) {
            throw ExpressComponentsException::orderNotFound($orderId);
        }

        return $order;
    }
}
