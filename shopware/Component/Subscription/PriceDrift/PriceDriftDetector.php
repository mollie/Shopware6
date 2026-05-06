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
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PriceDriftDetector
{
    public const STATE_NONE = 'none';
    public const STATE_NOTIFIED = 'notified';

    /**
     * Cap candidates per scheduled run. The task runs daily so a backlog still
     * drains within days even on shops with many subscriptions, but each run
     * stays bounded in DB load and event dispatch volume.
     */
    public const CANDIDATE_LIMIT = 50;

    private const EPSILON = 0.005;

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: 'customer.repository')]
        private readonly EntityRepository $customerRepository,
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

        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();

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

        /** @var array<int,array<string,mixed>> $upsertPayloads */
        $upsertPayloads = [];
        /** @var array<int,array{0:SubscriptionEntity,1:CustomerEntity}> $eventQueue */
        $eventQueue = [];

        foreach ($this->findCandidates($salesChannel->getId(), $context) as $subscription) {
            if (! $this->isMollieActive($subscription)) {
                continue;
            }
            if ($subscription->getPriceUpdateState() !== self::STATE_NONE) {
                continue;
            }
            if ($subscription->getCanceledAt() !== null) {
                continue;
            }

            $result = $this->checkOne($subscription, $context);
            if ($result === null) {
                continue;
            }

            $upsertPayloads[] = $result['payload'];
            if (isset($result['event'])) {
                $eventQueue[] = $result['event'];
            }
        }

        if ($upsertPayloads !== []) {
            $this->subscriptionRepository->upsert($upsertPayloads, $context);
        }

        foreach ($eventQueue as [$subscription, $customer]) {
            $this->eventDispatcher->dispatch(new SubscriptionPriceChangeNoticeEvent($subscription, $customer, $context));
        }

        return count($eventQueue);
    }

    /**
     * @return null|array{payload:array<string,mixed>,event?:array{0:SubscriptionEntity,1:CustomerEntity}}
     */
    private function checkOne(SubscriptionEntity $subscription, Context $context): ?array
    {
        try {
            $order = $subscription->getOrder();
            if (! $order instanceof OrderEntity) {
                throw new \RuntimeException('Subscription has no order loaded');
            }

            $intervalKey = (string) $subscription->getMetadata()->getInterval();
            $groupCart = $this->groupCartBuilder->buildGroupCart($order, $intervalKey, $context);
            if ($groupCart === null) {
                throw new \RuntimeException(sprintf('Failed to build cart for interval "%s"', $intervalKey));
            }

            $expectedAmount = SubscriptionGroupAmount::fromGroupCart($groupCart)->gross();
            $currentAmount = $subscription->getAmount();

            if (abs($expectedAmount - $currentAmount) < self::EPSILON) {
                return null;
            }

            $customer = $this->loadCustomer($subscription->getCustomerId(), $context);
            if (! $customer instanceof CustomerEntity) {
                $this->logger->error('Shopware customer not found for subscription, cannot send price change notice', [
                    'subscriptionId' => $subscription->getId(),
                    'customerId' => $subscription->getCustomerId(),
                ]);

                return null;
            }

            $now = new \DateTime();
            $subscription->setPriceUpdateState(self::STATE_NOTIFIED);
            $subscription->setNextNotifiedPrice($expectedAmount);
            $subscription->setNotifiedAt($now);

            return [
                'payload' => [
                    'id' => $subscription->getId(),
                    'priceUpdateState' => self::STATE_NOTIFIED,
                    'nextNotifiedPrice' => $expectedAmount,
                    'notifiedAt' => $now,
                    'historyEntries' => [[
                        'statusFrom' => $subscription->getStatus(),
                        'statusTo' => $subscription->getStatus(),
                        'comment' => sprintf('price_notified: %s -> %s', $currentAmount, $expectedAmount),
                        'mollieId' => $subscription->getMollieId(),
                    ]],
                ],
                'event' => [$subscription, $customer],
            ];
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to check subscription for price drift', [
                'subscriptionId' => $subscription->getId(),
                'mollieId' => $subscription->getMollieId(),
                'customerId' => $subscription->getCustomerId(),
                'orderId' => $subscription->getOrderId(),
                'exception' => $exception->getMessage(),
            ]);

            return [
                'payload' => [
                    'id' => $subscription->getId(),
                    'historyEntries' => [[
                        'statusFrom' => $subscription->getStatus(),
                        'statusTo' => $subscription->getStatus(),
                        'comment' => 'price_check_skipped: ' . $exception->getMessage(),
                        'mollieId' => $subscription->getMollieId(),
                    ]],
                ],
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
        $criteria->addFilter(new EqualsFilter('priceUpdateState', self::STATE_NONE));
        $criteria->addAssociation('order.lineItems');
        $criteria->setLimit(self::CANDIDATE_LIMIT);

        return $this->subscriptionRepository->search($criteria, $context)->getEntities();
    }

    private function isMollieActive(SubscriptionEntity $subscription): bool
    {
        $status = $subscription->getStatus();

        return $status === SubscriptionStatus::ACTIVE->value
            || $status === SubscriptionStatus::RESUMED->value;
    }

    private function loadCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('defaultBillingAddress');

        return $this->customerRepository->search($criteria, $context)->first();
    }
}
