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
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
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
        $criteria->getAssociation('transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1)
        ;
        $criteria->addAssociation('mollieSubscriptions');

        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order === null) {
            return new JsonResponse(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $latestTransaction = $order->getTransactions()?->first();

        if ($latestTransaction === null) {
            return new JsonResponse(['isMollieOrder' => false]);
        }

        /** @var null|Payment $payment */
        $payment = $latestTransaction->getExtension(Mollie::EXTENSION);

        if (! $payment instanceof Payment) {
            $payment = $this->restorePaymentFromOrderCustomFields($order, $latestTransaction, $context);

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
                $this->buildShippingStatus($mollieOrderId, $mollieOrder),
            ),
            $this->buildCancelStatus($mollieOrderId, $mollieOrder),
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
    private function buildCancelStatus(string $mollieOrderId, ?Order $mollieOrder): array
    {
        if ($mollieOrder === null) {
            return [];
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
    private function buildShippingStatus(string $mollieOrderId, ?Order $mollieOrder): array
    {
        if ($mollieOrder === null) {
            return [];
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
        if ($paymentId === '') {
            return null;
        }

        $payment = new Payment($paymentId);

        $orderId = (string) ($orderMollieFields['order_id'] ?? '');
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
