<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Order\Admin\Response\CancelStatusEntry;
use Mollie\Shopware\Component\Order\Admin\Response\OrderDetailsResponse;
use Mollie\Shopware\Component\Order\Admin\Response\RefundManagerConfig;
use Mollie\Shopware\Component\Order\Admin\Response\ShippingData;
use Mollie\Shopware\Component\Order\Admin\Response\ShippingStatusEntry;
use Mollie\Shopware\Component\Order\Admin\Response\ShippingTotal;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
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
            $payment = $this->restorePaymentFromOrderCustomFields($order, $effectiveTransaction, $context);

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
                $this->buildShippingTotal($mollieOrder),
                $this->buildShippingStatus($mollieOrderId, $mollieOrder, $order->getLineItems(), $shippingAllowed, $order->getDeliveries()),
            ),
            $this->buildCancelStatus($mollieOrderId, $mollieOrder, $order->getLineItems(), $shippingAllowed),
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

    /**
     * @return array<string, CancelStatusEntry>
     */
    private function buildCancelStatus(string $mollieOrderId, ?Order $mollieOrder, ?OrderLineItemCollection $lineItems, bool $shippingAllowed): array
    {
        if ($mollieOrder === null) {
            if ($lineItems === null) {
                return [];
            }

            $result = [];
            foreach ($lineItems as $lineItem) {
                $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
                $shipped = (int) ($fields['quantity'] ?? 0);
                $cancelled = (int) ($fields['cancelled_quantity'] ?? 0);
                $cancelable = $shippingAllowed ? max(0, $lineItem->getQuantity() - $shipped - $cancelled) : 0;
                $result[$lineItem->getId()] = new CancelStatusEntry(
                    '',
                    $lineItem->getId(),
                    $cancelable > 0,
                    $cancelable,
                    $cancelled,
                );
            }

            return $result;
        }

        $result = [];
        foreach ($mollieOrder->getLines() as $line) {
            $shopwareLineItemId = $line->getShopwareLineItemId();
            if ($shopwareLineItemId === '') {
                continue;
            }
            $result[$shopwareLineItemId] = new CancelStatusEntry(
                $mollieOrderId,
                $line->getId(),
                $line->getCancelableQuantity() > 0,
                $line->getCancelableQuantity(),
                $line->getQuantityCanceled(),
            );
        }

        return $result;
    }

    /**
     * @return array<string, ShippingStatusEntry>
     */
    private function buildShippingStatus(string $mollieOrderId, ?Order $mollieOrder, ?OrderLineItemCollection $lineItems, bool $shippingAllowed, ?OrderDeliveryCollection $deliveries = null): array
    {
        if ($mollieOrder === null) {
            if ($lineItems === null) {
                return [];
            }

            $result = [];
            foreach ($lineItems as $lineItem) {
                $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
                $shippedQty = (int) ($fields['quantity'] ?? 0);
                $cancelledQty = (int) ($fields['cancelled_quantity'] ?? 0);
                $shippableQty = $shippingAllowed ? max(0, $lineItem->getQuantity() - $shippedQty - $cancelledQty) : 0;
                $result[$lineItem->getId()] = new ShippingStatusEntry(
                    '',
                    '',
                    $shippableQty > 0,
                    $shippableQty,
                    $shippedQty,
                );
            }

            foreach ($deliveries ?? [] as $delivery) {
                $fields = $delivery->getCustomFields()[Mollie::EXTENSION] ?? [];
                $shippedQty = (int) ($fields['quantity'] ?? 0);
                $totalQty = $delivery->getShippingCosts()->getQuantity();
                $shippableQty = $shippingAllowed ? max(0, $totalQty - $shippedQty) : 0;
                $result[$delivery->getId()] = new ShippingStatusEntry(
                    '',
                    '',
                    $shippableQty > 0,
                    $shippableQty,
                    $shippedQty,
                );
            }

            return $result;
        }

        $result = [];
        foreach ($mollieOrder->getLines() as $line) {
            $shopwareLineItemId = $line->getShopwareLineItemId();
            if ($shopwareLineItemId === '') {
                continue;
            }
            $result[$shopwareLineItemId] = new ShippingStatusEntry(
                $mollieOrderId,
                $line->getId(),
                $line->getShippableQuantity() > 0,
                $line->getShippableQuantity(),
                $line->getQuantityShipped(),
            );
        }

        return $result;
    }

    private function buildShippingTotal(?Order $mollieOrder): ShippingTotal
    {
        if ($mollieOrder === null) {
            return new ShippingTotal('0.00', 0, 0);
        }

        $totalAmount = 0.0;
        $totalQuantity = 0;
        $totalShippable = 0;

        foreach ($mollieOrder->getLines() as $line) {
            $amountShipped = $line->getAmountShipped();
            if ($amountShipped !== null) {
                $totalAmount += (float) $amountShipped->getValue();
            }
            $totalQuantity += $line->getQuantityShipped();
            $totalShippable += $line->getShippableQuantity();
        }

        return new ShippingTotal(
            number_format(round($totalAmount, 2), 2),
            $totalQuantity,
            $totalShippable,
        );
    }

    private function restorePaymentFromOrderCustomFields(OrderEntity $order, OrderTransactionEntity $transaction, Context $context): ?Payment
    {
        $orderMollieFields = ($order->getCustomFields() ?? [])[Mollie::EXTENSION] ?? [];

        $paymentId = (string) ($orderMollieFields['payment_id'] ?? '');
        $orderId = (string) ($orderMollieFields['order_id'] ?? '');

        if ($paymentId === '' && $orderId === '') {
            return null;
        }

        $payment = new Payment($paymentId);

        if ($orderId !== '') {
            $payment->setOrderId($orderId);
        }

        $method = (string) ($orderMollieFields['payment_method'] ?? '');
        $paymentMethod = $method !== '' ? PaymentMethod::tryFrom($method) : null;
        if ($paymentMethod !== null) {
            $payment->setMethod($paymentMethod);
        }

        $thirdPartyPaymentId = (string) ($orderMollieFields['third_party_payment_id'] ?? '');
        if ($thirdPartyPaymentId !== '') {
            $payment->setThirdPartyPaymentId($thirdPartyPaymentId);
        }

        $checkoutUrl = (string) ($orderMollieFields['molliePaymentUrl'] ?? '');
        if ($checkoutUrl !== '') {
            $payment->setCheckoutUrl($checkoutUrl);
        }

        $creditCardLabel = (string) ($orderMollieFields['creditCardLabel'] ?? '');
        if ($creditCardLabel !== '') {
            $payment->setCreditCardLabel($creditCardLabel);
            $payment->setCreditCardNumber((string) ($orderMollieFields['creditCardNumber'] ?? ''));
            $payment->setCreditCardHolder((string) ($orderMollieFields['creditCardHolder'] ?? ''));
        }

        $paypalPayerId = (string) ($orderMollieFields['paypalPayerId'] ?? '');
        if ($paypalPayerId !== '') {
            $payment->setPaypalPayerId($paypalPayerId);
        }

        $bankAccount = (string) ($orderMollieFields['bankAccount'] ?? '');
        if ($bankAccount !== '') {
            $payment->setBankName((string) ($orderMollieFields['bankName'] ?? ''));
            $payment->setBankAccount($bankAccount);
            $payment->setBankBic((string) ($orderMollieFields['bankBic'] ?? ''));
            $payment->setTransferReference((string) ($orderMollieFields['transferReference'] ?? ''));
            $payment->setConsumerName((string) ($orderMollieFields['consumerName'] ?? ''));
            $payment->setConsumerAccount((string) ($orderMollieFields['consumerAccount'] ?? ''));
            $payment->setConsumerBic((string) ($orderMollieFields['consumerBic'] ?? ''));
        }

        $this->orderRepository->upsert([
            [
                'id' => $order->getId(),
                'transactions' => [
                    [
                        'id' => $transaction->getId(),
                        'customFields' => [Mollie::EXTENSION => $payment->toArray()],
                    ],
                ],
            ],
        ], $context);

        return $payment;
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
