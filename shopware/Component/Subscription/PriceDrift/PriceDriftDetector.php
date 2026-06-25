<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\PriceDrift;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionPriceChangeNoticeEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupAmount;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCartBuilder;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCartBuilderInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PriceDriftDetector
{
    public const STATE_NONE = 'none';
    public const STATE_NOTIFIED = 'notified';

    /**
     * Marks a subscription whose underlying price (product or shipping) changed
     * and that therefore needs a price re-check. Set cheaply and event-driven
     * by SubscriptionPriceCheckFlagger; only these are processed by detect().
     */
    public const STATE_DIRTY = 'dirty';

    /**
     * Cap candidates per scheduled run. The task runs every few minutes, so a
     * backlog drains quickly while each run stays bounded in DB load, cart
     * builds and event dispatch volume.
     */
    public const CANDIDATE_LIMIT = 50;

    /**
     * Two amounts are treated as equal when they differ by less than half a cent.
     * Prices are floats with a 1 cent (0.01) granularity, so a smaller difference
     * is only floating-point noise, not a real price change.
     */
    private const AMOUNT_EQUALITY_TOLERANCE = 0.005;

    /**
     * Price change notice events collected during a sales channel run; dispatched
     * after the batched upsert so the flow's storer reloads the persisted price.
     *
     * @var array<int,SubscriptionPriceChangeNoticeEvent>
     */
    private array $eventsToDispatch = [];

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: SubscriptionGroupCartBuilder::class)]
        private readonly SubscriptionGroupCartBuilderInterface $groupCartBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function detect(Context $context): int
    {
        $notifiedCount = 0;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        foreach ($salesChannels as $salesChannel) {
            $notifiedCount += $this->detectForSalesChannel($salesChannel, $context);
        }

        return $notifiedCount;
    }

    private function detectForSalesChannel(SalesChannelEntity $salesChannel, Context $context): int
    {
        $settings = $this->settingsService->getSubscriptionSettings($salesChannel->getId());
        if (! $settings->isEnabled()) {
            return 0;
        }
        if (! $settings->isAutoPriceUpdate()) {
            return 0;
        }

        $this->logger->info(sprintf('Starting subscription price drift detection for sales channel "%s"', $salesChannel->getName()));

        // $eventsToDispatch is empty here: it is cleared again after each sales
        // channel's dispatch below (and starts empty).

        /** @var array<int,array<string,mixed>> $upsertPayloads */
        $upsertPayloads = [];

        $candidates = $this->findCandidates($salesChannel->getId(), $context);

        foreach ($candidates as $subscription) {
            $order = $subscription->getOrder();
            if (! $order instanceof OrderEntity) {
                $this->logger->error('Subscription has no order loaded, skipping price drift check', [
                    'subscriptionId' => $subscription->getId(),
                ]);
                continue;
            }

            $customer = $order->getOrderCustomer()?->getCustomer();
            if (! $customer instanceof CustomerEntity) {
                $this->logger->error('Shopware customer not found for subscription, cannot send price change notice', [
                    'subscriptionId' => $subscription->getId(),
                    'orderNumber' => $order->getOrderNumber(),
                ]);
                continue;
            }

            $upsertPayloads[] = $this->checkOne($subscription, $order, $customer, $context);
        }

        // Persist everything in one batch first, then dispatch so the flow's
        // storer reloads the persisted nextNotifiedPrice when rendering the mail.
        if ($upsertPayloads !== []) {
            $this->subscriptionRepository->upsert($upsertPayloads, $context);
        }

        foreach ($this->eventsToDispatch as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        $notifiedCount = count($this->eventsToDispatch);
        $this->eventsToDispatch = [];

        return $notifiedCount;
    }

    /**
     * Checks one subscription for price drift and returns the upsert payload. A
     * price change notice event is queued in $eventsToDispatch and dispatched by
     * the caller after the batch.
     *
     * @return array<string,mixed>
     */
    private function checkOne(SubscriptionEntity $subscription, OrderEntity $order, CustomerEntity $customer, Context $context): array
    {
        $logData = [
            'subscriptionId' => $subscription->getId(),
            'orderNumber' => (string) $order->getOrderNumber(),
            'mollieId' => $subscription->getMollieId(),
        ];

        try {
            $intervalKey = (string) $subscription->getMetadata()->getInterval();
            $groupCart = $this->groupCartBuilder->buildGroupCart($order, $intervalKey, $context);
            if ($groupCart === null) {
                throw new \RuntimeException(sprintf('Failed to build cart for interval "%s"', $intervalKey));
            }

            $expectedAmount = SubscriptionGroupAmount::fromGroupCart($groupCart)->gross();
            $currentAmount = $subscription->getAmount();

            if (abs($expectedAmount - $currentAmount) < self::AMOUNT_EQUALITY_TOLERANCE) {
                // Price matches again (e.g. the change was reverted) — clear the
                // dirty flag so the subscription is not re-checked every run.
                $this->logger->debug('No subscription price drift detected, clearing dirty flag', $logData);

                return [
                    'id' => $subscription->getId(),
                    'priceUpdateState' => self::STATE_NONE,
                ];
            }

            $this->eventsToDispatch[] = new SubscriptionPriceChangeNoticeEvent($subscription, $customer, $context);
            $this->logger->info('Subscription price drift detected, queuing price change notice', $logData + [
                'currentAmount' => $currentAmount,
                'newAmount' => $expectedAmount,
            ]);

            return [
                'id' => $subscription->getId(),
                'priceUpdateState' => self::STATE_NOTIFIED,
                'nextNotifiedPrice' => $expectedAmount,
                'notifiedAt' => new \DateTime(),
                'historyEntries' => [[
                    'statusFrom' => $subscription->getStatus(),
                    'statusTo' => $subscription->getStatus(),
                    'comment' => sprintf('price_notified: %s -> %s', $currentAmount, $expectedAmount),
                    'mollieId' => $subscription->getMollieId(),
                ]],
            ];
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to check subscription for price drift', $logData + [
                'exception' => $exception->getMessage(),
            ]);

            // Clear the dirty flag so a broken subscription is not retried every
            // run; a later price change re-flags it.
            return [
                'id' => $subscription->getId(),
                'priceUpdateState' => self::STATE_NONE,
                'historyEntries' => [[
                    'statusFrom' => $subscription->getStatus(),
                    'statusTo' => $subscription->getStatus(),
                    'comment' => 'price_check_skipped: ' . $exception->getMessage(),
                    'mollieId' => $subscription->getMollieId(),
                ]],
            ];
        }
    }

    /**
     * @return iterable<SubscriptionEntity>
     */
    private function findCandidates(string $salesChannelId, Context $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('canceledAt', null));
        $criteria->addFilter(new EqualsFilter('priceUpdateState', self::STATE_DIRTY));
        $criteria->addFilter(new EqualsAnyFilter('status', [
            SubscriptionStatus::ACTIVE->value,
            SubscriptionStatus::RESUMED->value,
        ]));
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.deliveries');
        $criteria->addAssociation('order.transactions');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(self::CANDIDATE_LIMIT);

        return $this->subscriptionRepository->search($criteria, $context)->getEntities();
    }
}
