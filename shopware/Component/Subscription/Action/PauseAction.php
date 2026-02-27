<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action;

use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\Exception\PauseAndResumeNotAllowedException;
use Mollie\Shopware\Component\Subscription\Action\Exception\SubscriptionNotActiveException;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionActionEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionPausedEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PauseAction extends AbstractAction
{
    private const ACTION_NAME = 'pause';

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

    public static function getActioName(): string
    {
        return self::ACTION_NAME;
    }

    /**
     * @return class-string<SubscriptionActionEvent>
     */
    public function getEventClass(): string
    {
        return SubscriptionPausedEvent::class;
    }

    public function execute(SubscriptionDataStruct $subscriptionData, SubscriptionSettings $settings, Subscription $mollieSubscription, string $orderNumber, Context $context): Subscription
    {
        $subscription = $subscriptionData->getSubscription();
        $subscriptionId = $subscription->getId();
        $salesChannelId = $subscription->getSalesChannelId();
        $mollieSubscriptionId = $subscription->getMollieId();
        $mollieCustomerId = $subscription->getMollieCustomerId();
        $shopwareSubscriptionStatus = SubscriptionStatus::from($subscription->getStatus());
        $mollieSubscriptionStatus = $mollieSubscription->getStatus();
        $logData = [
            'subscriptionId' => $subscriptionId,
            'salesChannelId' => $salesChannelId,
            'mollieSubscriptionId' => $mollieSubscriptionId,
            'mollieCustomerId' => $mollieCustomerId,
            'orderNumber' => $orderNumber,
            'shopwareSubscriptionStatus' => $shopwareSubscriptionStatus->value,
            'mollieSubscriptionStatus' => $mollieSubscriptionStatus->value
        ];

        $this->logger->info('Starting to pause subscription', $logData);

        if (! $settings->isAllowPauseAndResume()) {
            $this->logger->error('Pausing subscriptions is disabled in the settings', $logData);
            throw new PauseAndResumeNotAllowedException($salesChannelId);
        }

        if (! $mollieSubscriptionStatus->isActive()) {
            $this->logger->error('Subscription status is not active', $logData);
            throw new SubscriptionNotActiveException($subscriptionId);
        }

        $today = new \DateTime();
        $newStatus = SubscriptionStatus::PAUSED_AFTER_RENEWAL;
        $canceledAt = null;
        $nextPaymentAt = $mollieSubscription->getNextPaymentDate() ?? $today;
        $nextPaymentAtDate = $nextPaymentAt->format('Y-m-d');

        $metaData = $subscription->getMetadata();
        $metaData->setNextPossiblePaymentDate($nextPaymentAtDate);

        $subscriptionHistories = [
            'statusFrom' => $shopwareSubscriptionStatus->value,
            'statusTo' => $newStatus->value,
            'mollieId' => $mollieSubscriptionId,
            'comment' => 'paused after ' . $nextPaymentAtDate
        ];

        if ($mollieSubscription->isStateChangeWindowOpen($today, $settings->getCancelDays())) {
            $newStatus = SubscriptionStatus::PAUSED;
            $canceledAt = $today;
            $nextPaymentAt = null;
            $subscriptionHistories['comment'] = 'paused';
            $subscriptionHistories['statusTo'] = $newStatus->value;
            $this->logger->info('Subscription will be paused immediately', $logData);
            $mollieSubscription = $this->mollieGateway->cancelSubscription($mollieSubscriptionId, $mollieCustomerId, $orderNumber, $salesChannelId);
        }

        $upsertData = [
            'id' => $subscriptionId,
            'status' => $newStatus->value,
            'nextPaymentAt' => $nextPaymentAt,
            'canceledAt' => $canceledAt,
            'metadata' => $metaData->toArray(),
            'historyEntries' => [$subscriptionHistories]
        ];

        $this->subscriptionRepository->upsert([$upsertData], $context);
        $this->logger->info('Pause subscription finished', $logData);

        return $mollieSubscription;
    }
}
