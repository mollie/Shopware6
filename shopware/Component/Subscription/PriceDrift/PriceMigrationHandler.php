<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\PriceDrift;

use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Subscription as MollieSubscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PriceMigrationHandler
{
    /**
     * Cap candidates per scheduled run. The task runs daily, so a backlog still
     * drains within days even on shops with many subscriptions, but each run
     * makes at most CANDIDATE_LIMIT PATCH calls and a single batched DB write.
     */
    public const CANDIDATE_LIMIT = 50;

    private const MOLLIE_LIST_PAGE_SIZE = 250;

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
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function migrate(Context $context): int
    {
        $today = new \DateTimeImmutable();
        $migratedCount = 0;

        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();

        foreach ($salesChannels as $salesChannel) {
            $migratedCount += $this->migrateForSalesChannel($salesChannel, $today, $context);
        }

        return $migratedCount;
    }

    private function migrateForSalesChannel(SalesChannelEntity $salesChannel, \DateTimeImmutable $today, Context $context): int
    {
        $settings = $this->settingsService->getSubscriptionSettings($salesChannel->getId());
        if (! $settings->isEnabled()) {
            return 0;
        }
        if (! $settings->isAutoPriceUpdate()) {
            return 0;
        }

        $candidates = [];
        foreach ($this->findCandidates($salesChannel->getId(), $context) as $subscription) {
            if (! $this->isMollieActive($subscription)) {
                continue;
            }
            if ($subscription->getPriceUpdateState() !== PriceDriftDetector::STATE_NOTIFIED) {
                continue;
            }
            if ($subscription->getCanceledAt() !== null) {
                continue;
            }
            if (! $this->isNoticeWindowElapsed($subscription, $settings, $today)) {
                continue;
            }

            $candidates[] = $subscription;
        }

        if ($candidates === []) {
            return 0;
        }

        $this->logger->info(sprintf('Starting subscription price migration for sales channel "%s"', $salesChannel->getName()), [
            'candidateCount' => count($candidates),
        ]);

        $mollieSubscriptionsByMollieId = $this->bulkLoadMollieSubscriptions($candidates, $salesChannel->getId());

        /** @var array<int,array<string,mixed>> $upsertPayloads */
        $upsertPayloads = [];
        $migratedCount = 0;

        foreach ($candidates as $subscription) {
            $payload = $this->migrateOne($subscription, $mollieSubscriptionsByMollieId);
            if ($payload === null) {
                continue;
            }

            $upsertPayloads[] = $payload['payload'];
            if ($payload['migrated']) {
                ++$migratedCount;
            }
        }

        if ($upsertPayloads !== []) {
            $this->subscriptionRepository->upsert($upsertPayloads, $context);
        }

        return $migratedCount;
    }

    /**
     * @param array<string,MollieSubscription> $mollieSubscriptionsByMollieId
     *
     * @return null|array{migrated:bool,payload:array<string,mixed>}
     */
    private function migrateOne(SubscriptionEntity $subscription, array $mollieSubscriptionsByMollieId): ?array
    {
        $newAmount = $subscription->getNextNotifiedPrice();
        if ($newAmount === null) {
            $this->logger->error('Cannot migrate subscription without a notified price', [
                'subscriptionId' => $subscription->getId(),
            ]);

            return null;
        }

        $orderNumber = (string) ($subscription->getOrder()?->getOrderNumber() ?? '');

        try {
            $mollieSubscription = $mollieSubscriptionsByMollieId[$subscription->getMollieId()] ?? null;
            if (! $mollieSubscription instanceof MollieSubscription) {
                throw new \RuntimeException(sprintf('Mollie subscription "%s" was not returned by listSubscriptions', $subscription->getMollieId()));
            }

            $newMoney = new Money($newAmount, $mollieSubscription->getAmount()->getCurrency());
            $mollieSubscription->setAmount($newMoney);

            $this->subscriptionGateway->updateSubscription(
                $mollieSubscription,
                $subscription->getMollieCustomerId(),
                $orderNumber,
                $subscription->getSalesChannelId()
            );

            return [
                'migrated' => true,
                'payload' => [
                    'id' => $subscription->getId(),
                    'amount' => $newAmount,
                    'priceUpdateState' => PriceDriftDetector::STATE_NONE,
                    'nextNotifiedPrice' => null,
                    'notifiedAt' => null,
                    'historyEntries' => [[
                        'statusFrom' => $subscription->getStatus(),
                        'statusTo' => $subscription->getStatus(),
                        'comment' => sprintf('price_migrated: %s', $newAmount),
                        'mollieId' => $subscription->getMollieId(),
                    ]],
                ],
            ];
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to migrate subscription price', [
                'subscriptionId' => $subscription->getId(),
                'mollieId' => $subscription->getMollieId(),
                'customerId' => $subscription->getCustomerId(),
                'orderId' => $subscription->getOrderId(),
                'exception' => $exception->getMessage(),
            ]);

            return [
                'migrated' => false,
                'payload' => [
                    'id' => $subscription->getId(),
                    'historyEntries' => [[
                        'statusFrom' => $subscription->getStatus(),
                        'statusTo' => $subscription->getStatus(),
                        'comment' => 'price_migration_failed: ' . $exception->getMessage(),
                        'mollieId' => $subscription->getMollieId(),
                    ]],
                ],
            ];
        }
    }

    /**
     * Loads all needed Mollie subscriptions in as few API calls as possible.
     * Uses Mollie's `from` cursor pagination on the profile-level
     * `/subscriptions` endpoint (Mollie sorts the response by ID ascending).
     * Candidates arrive sorted by `notifiedAt` ASC, so the first candidate's
     * ID is the cursor entry point. We never compare Mollie IDs ourselves —
     * pagination uses whatever ID Mollie returned last in the previous page.
     *
     * @param list<SubscriptionEntity> $candidates
     *
     * @return array<string,MollieSubscription>
     */
    private function bulkLoadMollieSubscriptions(array $candidates, string $salesChannelId): array
    {
        $neededIds = [];
        foreach ($candidates as $candidate) {
            $neededIds[$candidate->getMollieId()] = true;
        }

        $result = [];
        $from = $candidates[0]->getMollieId();

        while ($from !== null && $neededIds !== []) {
            try {
                $page = $this->subscriptionGateway->listSubscriptions($from, self::MOLLIE_LIST_PAGE_SIZE, $salesChannelId);
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to list Mollie subscriptions for bulk load', [
                    'salesChannelId' => $salesChannelId,
                    'from' => $from,
                    'exception' => $exception->getMessage(),
                ]);
                break;
            }

            if ($page->count() === 0) {
                break;
            }

            $lastSeenId = null;
            foreach ($page as $mollieSubscription) {
                $id = $mollieSubscription->getId();
                if (isset($neededIds[$id])) {
                    $result[$id] = $mollieSubscription;
                    unset($neededIds[$id]);
                }
                $lastSeenId = $id;
            }

            if ($neededIds === [] || $page->count() < self::MOLLIE_LIST_PAGE_SIZE) {
                break;
            }

            $from = $lastSeenId;
        }

        return $result;
    }

    private function isNoticeWindowElapsed(SubscriptionEntity $subscription, SubscriptionSettings $settings, \DateTimeImmutable $today): bool
    {
        $notifiedAt = $subscription->getNotifiedAt();
        if (! $notifiedAt instanceof \DateTimeInterface) {
            return false;
        }

        $earliestMigration = (new \DateTimeImmutable($notifiedAt->format('Y-m-d H:i:s')))
            ->modify(sprintf('+%d day', $settings->getPriceUpdateNoticeDays()))
        ;

        return $today >= $earliestMigration;
    }

    /**
     * @return iterable<SubscriptionEntity>
     */
    private function findCandidates(string $salesChannelId, Context $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('canceledAt', null));
        $criteria->addFilter(new EqualsFilter('priceUpdateState', PriceDriftDetector::STATE_NOTIFIED));
        $criteria->addAssociation('order');
        $criteria->addSorting(new FieldSorting('notifiedAt', FieldSorting::ASCENDING));
        $criteria->setLimit(self::CANDIDATE_LIMIT);

        return $this->subscriptionRepository->search($criteria, $context)->getEntities();
    }

    private function isMollieActive(SubscriptionEntity $subscription): bool
    {
        $status = $subscription->getStatus();

        return $status === SubscriptionStatus::ACTIVE->value
            || $status === SubscriptionStatus::RESUMED->value;
    }
}
