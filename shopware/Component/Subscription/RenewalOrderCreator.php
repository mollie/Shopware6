<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Route\RenewException;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RenewalOrderCreator
{
    /**
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: SubscriptionGroupCartBuilder::class)]
        private readonly SubscriptionGroupCartBuilderInterface $groupCartBuilder,
        #[Autowire(service: CartOrderRoute::class)]
        private readonly AbstractCartOrderRoute $cartOrderRoute,
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function create(
        OrderEntity $originalOrder,
        string $subscriptionId,
        string $intervalKey,
        RenewalAddresses $addresses,
        Payment $molliePayment,
        Context $context
    ): OrderTransactionEntity {
        $groupCart = $this->groupCartBuilder->buildGroupCart($originalOrder, $intervalKey, $context, $addresses);
        if ($groupCart === null) {
            $this->logger->error('Failed to build renewal cart for subscription group', [
                'subscriptionId' => $subscriptionId,
                'intervalKey' => $intervalKey,
            ]);
            throw RenewException::invalidPaymentId($subscriptionId, (string) $molliePayment->getId());
        }

        $orderResponse = $this->cartOrderRoute->order(
            $groupCart->getCart(),
            $groupCart->getSalesChannelContext(),
            (new DataBag())->toRequestDataBag()
        );

        $newOrder = $orderResponse->getOrder();
        $transaction = $newOrder->getTransactions()?->first();
        if (! $transaction instanceof OrderTransactionEntity) {
            $this->logger->error('Renewal order has no transaction', [
                'subscriptionId' => $subscriptionId,
                'orderId' => $newOrder->getId(),
            ]);
            throw RenewException::orderWithoutTransaction($subscriptionId, (string) $newOrder->getOrderNumber());
        }

        return $this->linkTransaction($newOrder, $transaction, $molliePayment, $context);
    }

    public function linkExistingOrder(string $orderId, SubscriptionEntity $subscription, Payment $molliePayment, Context $context): OrderTransactionEntity
    {
        $subscriptionId = $subscription->getId();
        $logData = [
            'subscriptionId' => $subscriptionId,
            'orderId' => $orderId,
        ];

        if (! Uuid::isValid($orderId)) {
            $this->logger->error('Order to link with the subscription renewal is not a valid id', $logData);
            throw RenewException::orderNotFound($subscriptionId, $orderId);
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addFilter(new EqualsFilter('orderCustomer.customerId', $subscription->getCustomerId()));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $subscription->getSalesChannelId()));
        $criteria->addAssociation('transactions.stateMachineState');

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();
        if (! $order instanceof OrderEntity) {
            $this->logger->error('Order to link with the subscription renewal was not found for this subscription', $logData);
            throw RenewException::orderNotFound($subscriptionId, $orderId);
        }

        $transactions = new MollieOrderTransactionCollection($order->getTransactions());
        $transaction = $transactions->getCurrentOrderTransaction();
        if (! $transaction instanceof OrderTransactionEntity) {
            $this->logger->error('Order to link with the subscription renewal has no transaction', $logData);
            throw RenewException::existingOrderWithoutTransaction($subscriptionId, $orderId);
        }

        $mollieFields = ($transaction->getCustomFields() ?? [])[Mollie::EXTENSION] ?? [];
        if (count($mollieFields) > 0) {
            $this->logger->error('Order to link with the subscription renewal already belongs to a mollie payment', $logData);
            throw RenewException::orderAlreadyLinked($subscriptionId, $orderId);
        }

        $this->logger->info('Subscription renewal is linked to an existing order', $logData);

        return $this->linkTransaction($order, $transaction, $molliePayment, $context);
    }

    private function linkTransaction(OrderEntity $order, OrderTransactionEntity $transaction, Payment $molliePayment, Context $context): OrderTransactionEntity
    {
        $this->orderRepository->upsert([[
            'id' => $order->getId(),
            'tags' => [
                ['id' => SubscriptionTag::ID],
            ],
            'transactions' => [[
                'id' => $transaction->getId(),
                'customFields' => [
                    Mollie::EXTENSION => $molliePayment->toArray(),
                ],
            ]],
        ]], $context);

        $molliePayment->setShopwareTransaction($transaction);

        return $transaction;
    }
}
