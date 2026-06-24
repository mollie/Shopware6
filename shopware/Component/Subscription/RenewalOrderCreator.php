<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Subscription\Route\RenewException;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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

        $this->orderRepository->upsert([[
            'id' => $newOrder->getId(),
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
