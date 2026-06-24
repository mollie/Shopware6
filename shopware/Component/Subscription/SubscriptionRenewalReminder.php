<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionRemindedEvent;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SubscriptionRenewalReminder
{
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
        private readonly ReminderValidator $reminderValidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function remind(Context $context): int
    {
        $today = new \DateTimeImmutable();
        $remindedCount = 0;

        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();

        foreach ($salesChannels as $salesChannel) {
            $remindedCount += $this->remindForSalesChannel($salesChannel, $today, $context);
        }

        return $remindedCount;
    }

    private function remindForSalesChannel(SalesChannelEntity $salesChannel, \DateTimeImmutable $today, Context $context): int
    {
        $settings = $this->settingsService->getSubscriptionSettings($salesChannel->getId());
        if (! $settings->isEnabled()) {
            return 0;
        }

        $this->logger->info(sprintf('Starting subscription renewal reminder for sales channel "%s"', $salesChannel->getName()));

        $remindedCount = 0;

        $candidates = $this->findCandidates($salesChannel->getId(), $today, $context);

        foreach ($candidates as $subscription) {
            if (! $this->isMollieActive($subscription)) {
                continue;
            }

            $shouldRemind = $this->reminderValidator->shouldRemind(
                $subscription->getNextPaymentAt(),
                $today,
                $settings->getReminderDays(),
                $subscription->getLastRemindedAt()
            );
            if (! $shouldRemind) {
                continue;
            }

            $customer = $this->loadCustomer($subscription->getCustomerId(), $context);
            if (! $customer instanceof CustomerEntity) {
                $this->logger->error('Shopware customer not found for subscription, cannot send reminder', [
                    'subscriptionId' => $subscription->getId(),
                    'customerId' => $subscription->getCustomerId(),
                ]);
                continue;
            }

            $this->eventDispatcher->dispatch(new SubscriptionRemindedEvent($subscription, $customer, $context));

            $this->subscriptionRepository->upsert([[
                'id' => $subscription->getId(),
                'lastRemindedAt' => new \DateTime(),
                'historyEntries' => [[
                    'statusFrom' => '',
                    'statusTo' => '',
                    'comment' => 'reminded about renewal',
                    'mollieId' => $subscription->getMollieId(),
                ]],
            ]], $context);

            ++$remindedCount;
        }

        return $remindedCount;
    }

    /**
     * @return iterable<SubscriptionEntity>
     */
    private function findCandidates(string $salesChannelId, \DateTimeImmutable $today, Context $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('canceledAt', null));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new RangeFilter('nextPaymentAt', ['gte' => $today->format('Y-m-d H:i:s')]));

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

        $result = $this->customerRepository->search($criteria, $context);

        return $result->first(); // @phpstan-ignore return.type
    }
}
