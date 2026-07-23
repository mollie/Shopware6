<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Order\Admin\Response\OrderDetailsResponse;
use Mollie\Shopware\Component\Order\Admin\Response\RefundManagerConfig;
use Mollie\Shopware\Component\Order\Admin\Response\ShippingData;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final class OrderAdminController extends AbstractController
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $mollieSettings,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: OrderAdminStatusBuilder::class)]
        private readonly OrderAdminStatusBuilder $statusBuilder,
        #[Autowire(service: OrderPaymentRecovery::class)]
        private readonly OrderPaymentRecovery $paymentRecovery,
    ) {
    }

    #[Route(
        path: '/api/_action/mollie/order/{orderId}/details',
        name: 'api.action.mollie.order.admin.details',
        methods: ['GET']
    )]
    public function details(string $orderId, Context $context): JsonResponse
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('mollieSubscriptions');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('deliveries');

        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order === null) {
            return new JsonResponse(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $transactions = new MollieOrderTransactionCollection($order->getTransactions());
        $effectiveTransaction = $transactions->getCurrentOrderTransaction();

        if ($effectiveTransaction === null) {
            return new JsonResponse(['isMollieOrder' => false]);
        }

        /** @var null|Payment $payment */
        $payment = $effectiveTransaction->getExtension(Mollie::EXTENSION);

        if (! $payment instanceof Payment) {
            $payment = $this->paymentRecovery->restore($order, $effectiveTransaction, $context);

            if ($payment === null) {
                return new JsonResponse(['isMollieOrder' => false]);
            }
        }

        /** @var Payment $payment */
        $salesChannelId = $order->getSalesChannelId();

        $mollieOrderId = $payment->getOrderId() ?? '';
        $mollieId = $mollieOrderId !== '' ? $mollieOrderId : $payment->getId();

        $checkoutUrl = $payment->getCheckoutUrl() ?: null;
        $creditCard = $payment->getCreditCardDetails();
        $paypal = $payment->getPaypalDetails();
        $bankTransfer = $payment->getBankTransferDetails();

        $subscriptions = $order->getExtension('mollieSubscriptions');
        $subscriptionId = $subscriptions instanceof SubscriptionCollection ? $subscriptions->first()?->getId() : null;
        $isSubscription = $subscriptionId !== null;

        $mollieOrder = $this->loadMollieOrder($mollieOrderId, $salesChannelId);

        // The Mollie payment status on the loaded extension is not reliable here (no API call, status not
        // hydrated), so for the Payments API we rely on the Shopware transaction state machine: shipping/
        // cancelling is only possible while the transaction is still open or authorized, not once it is paid.
        $transactionState = $effectiveTransaction->getStateMachineState()?->getTechnicalName() ?? '';
        $shippingAllowed = in_array($transactionState, [
            OrderTransactionStates::STATE_OPEN,
            OrderTransactionStates::STATE_AUTHORIZED,
        ], true);

        return new JsonResponse(new OrderDetailsResponse(
            $mollieId,
            $payment->getThirdPartyPaymentId() ?: null,
            $creditCard,
            $paypal,
            $bankTransfer,
            $checkoutUrl,
            $isSubscription,
            $subscriptionId,
            $this->mollieSettings->getSubscriptionSettings($salesChannelId)->isEnabled(),
            $this->buildRefundManagerConfig($salesChannelId),
            new ShippingData(
                $this->statusBuilder->buildShippingTotal($mollieOrder),
                $this->statusBuilder->buildShippingStatus($mollieOrderId, $mollieOrder, $order->getLineItems(), $shippingAllowed, $order->getDeliveries()),
            ),
            $this->statusBuilder->buildCancelStatus($mollieOrderId, $mollieOrder, $order->getLineItems(), $shippingAllowed),
        ));
    }

    private function loadMollieOrder(string $mollieOrderId, string $salesChannelId): ?Order
    {
        if ($mollieOrderId === '') {
            return null;
        }

        try {
            return $this->mollieGateway->getOrder($mollieOrderId, $salesChannelId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildRefundManagerConfig(string $salesChannelId): RefundManagerConfig
    {
        $settings = $this->mollieSettings->getRefundSettings($salesChannelId);

        return new RefundManagerConfig(
            $settings->isEnabled(),
            $settings->isAutoStockReset(),
            $settings->isVerifyRefund(),
            $settings->isShowInstructions(),
        );
    }
}
