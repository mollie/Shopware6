<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action;

use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\Exception\SubscriptionNotActiveException;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionCancelledEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CancelAction extends AbstractAction
{
    private const ACTION_NAME = 'cancel';

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     * @param SubscriptionGateway $mollieGateway
     */
    public function __construct(
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $mollieGateway,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(SubscriptionDataStruct $subscriptionData, SubscriptionSettings $settings, Subscription $mollieSubscription, string $orderNumber, Context $context): Subscription
    {
        $subscription = $subscriptionData->getSubscription();
        $shopwareStatus = $subscription->getStatus();
        $mollieSubscriptionStatus = $mollieSubscription->getStatus();

        $logData = [
            'subscriptionId' => $subscription->getId(),
            'salesChannelId' => $subscription->getSalesChannelId(),
            'mollieSubscriptionId' => $subscription->getMollieId(),
            'mollieCustomerId' => $subscription->getMollieCustomerId(),
            'orderNumber' => $orderNumber,
            'shopwareSubscriptionStatus' => $shopwareStatus,
            'mollieSubscriptionStatus' => $mollieSubscriptionStatus->value,
        ];

        $this->logger->info('Starting to cancel subscription', $logData);

        if ($shopwareStatus === SubscriptionStatus::PENDING->value) {
            $this->logger->info('Subscription is pending, persisting cancellation without Mollie API call', $logData);

            return $this->persistImmediateCancellation($subscription, $context, $mollieSubscription, 'canceled');
        }

        if (! $mollieSubscriptionStatus->isActive()) {
            $this->logger->error('Subscription status is not active', $logData);

            throw new SubscriptionNotActiveException($subscription->getId());
        }

        $today = new \DateTime();
        $nextPaymentAt = $mollieSubscription->getNextPaymentDate() ?? $today;

        $metaData = $subscription->getMetadata();
        $metaData->setNextPossiblePaymentDate($nextPaymentAt->format('Y-m-d'));

        if (! $mollieSubscription->isStateChangeWindowOpen($today, $settings->getCancelDays())) {
            return $this->persistDeferredCancellation($subscription, $nextPaymentAt, $metaData, $context, $mollieSubscription);
        }

        $this->logger->info('Subscription will be cancelled immediately', $logData);
        $mollieSubscription = $this->mollieGateway->cancelSubscription(
            $subscription->getMollieId(),
            $subscription->getMollieCustomerId(),
            $orderNumber,
            $subscription->getSalesChannelId(),
        );

        return $this->persistImmediateCancellation($subscription, $context, $mollieSubscription, 'cancelled', $metaData);
    }

    public function cancelPending(SubscriptionEntity $subscription, Context $context): void
    {
        if ($subscription->getStatus() !== SubscriptionStatus::PENDING->value) {
            throw new \LogicException(sprintf('CancelAction::cancelPending called for non-pending subscription "%s"', $subscription->getId()));
        }

        $this->persistImmediateCancellation($subscription, $context, null, 'canceled');
    }

    public function getEventClass(): string
    {
        return SubscriptionCancelledEvent::class;
    }

    public static function getActioName(): string
    {
        return self::ACTION_NAME;
    }

    private function persistImmediateCancellation(
        SubscriptionEntity $subscription,
        Context $context,
        ?Subscription $mollieSubscription,
        string $comment = 'cancelled',
        ?SubscriptionMetadata $metadata = null
    ): ?Subscription {
        $statusFrom = $subscription->getStatus();
        $newStatus = SubscriptionStatus::CANCELED->value;

        $upsertData = [
            'id' => $subscription->getId(),
            'status' => $newStatus,
            'canceledAt' => new \DateTime(),
            'nextPaymentAt' => null,
            'historyEntries' => [[
                'statusFrom' => $statusFrom,
                'statusTo' => $newStatus,
                'mollieId' => $subscription->getMollieId(),
                'comment' => $comment,
            ]],
        ];

        if ($metadata instanceof SubscriptionMetadata) {
            $upsertData['metadata'] = $metadata->toArray();
        }

        $this->subscriptionRepository->upsert([$upsertData], $context);

        return $mollieSubscription;
    }

    private function persistDeferredCancellation(
        SubscriptionEntity $subscription,
        \DateTimeInterface $nextPaymentAt,
        SubscriptionMetadata $metadata,
        Context $context,
        Subscription $mollieSubscription
    ): Subscription {
        $statusFrom = $subscription->getStatus();
        $newStatus = SubscriptionStatus::CANCELED_AFTER_RENEWAL->value;
        $nextPaymentAtDate = $nextPaymentAt->format('Y-m-d');

        $upsertData = [
            'id' => $subscription->getId(),
            'status' => $newStatus,
            'canceledAt' => null,
            'nextPaymentAt' => $nextPaymentAt,
            'metadata' => $metadata->toArray(),
            'historyEntries' => [[
                'statusFrom' => $statusFrom,
                'statusTo' => $newStatus,
                'mollieId' => $subscription->getMollieId(),
                'comment' => 'cancelled after ' . $nextPaymentAtDate,
            ]],
        ];

        $this->subscriptionRepository->upsert([$upsertData], $context);

        return $mollieSubscription;
    }
}
