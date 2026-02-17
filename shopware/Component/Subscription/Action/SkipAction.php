<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\Exception\NextPaymentAtNotFoundException;
use Mollie\Shopware\Component\Subscription\Action\Exception\PauseAndResumeNotAllowedException;
use Mollie\Shopware\Component\Subscription\Action\Exception\SubscriptionNotActiveException;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionActionEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionSkippedEvent;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SkipAction extends AbstractAction
{
    private const ACTION_NAME = 'skip';

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
        return SubscriptionSkippedEvent::class;
    }

    public function execute(SubscriptionEntity $subscription, SubscriptionSettings $settings, Subscription $mollieSubscription, string $orderNumber, Context $context): Subscription
    {
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

        $this->logger->info('Starting to skip subscription', $logData);

        if (! $settings->isAllowPauseAndResume()) {
            $this->logger->error('Skipping subscriptions is disabled in the settings', $logData);
            throw new PauseAndResumeNotAllowedException($salesChannelId);
        }

        if (! $mollieSubscriptionStatus->isActive()) {
            $this->logger->error('Subscription status is not active', $logData);
            throw new SubscriptionNotActiveException($subscriptionId);
        }

        $today = new \DateTime();
        $newStatus = SubscriptionStatus::SKIPPED_AFTER_RENEWAL;
        $nextPaymentAt = $mollieSubscription->getNextPaymentDate() ?? $today;

        $logData['nextPaymentDate'] = $nextPaymentAt->format('Y-m-d');

        $subscriptionHistories = [
            'statusFrom' => $shopwareSubscriptionStatus->value,
            'statusTo' => $newStatus->value,
            'comment' => 'skipped after ' . $nextPaymentAt->format('Y-m-d')
        ];

        if ($mollieSubscription->isStateChangeWindowOpen($today, $settings->getCancelDays())) {
            $newStatus = SubscriptionStatus::SKIPPED;
            $subscriptionHistories['comment'] = 'skipped';
            $subscriptionHistories['statusTo'] = $newStatus->value;

            $mollieSubscription->setStartDate($mollieSubscription->skipPayment());

            $mollieSubscription = $this->mollieGateway->cancelSubscription($mollieSubscriptionId, $mollieCustomerId, $orderNumber, $salesChannelId);

            $newSubscription = $this->mollieGateway->copySubscription($mollieSubscription, $mollieCustomerId, $orderNumber, $salesChannelId);

            $mollieSubscriptionId = $newSubscription->getId();
            $nextPaymentAt = $newSubscription->getNextPaymentDate();
            if (! $nextPaymentAt instanceof \DateTime) {
                throw new NextPaymentAtNotFoundException($mollieSubscriptionId);
            }
            $logData['newNextPaymentDate'] = $nextPaymentAt->format('Y-m-d');
            $logData['newMollieSubscriptionId'] = $mollieSubscriptionId;

            $this->logger->info('Subscription skipped immediately', $logData);
        }

        $subscriptionHistories['mollieId'] = $mollieSubscriptionId;

        $upsertData = [
            'id' => $subscriptionId,
            'status' => $newStatus->value,
            'mollieId' => $mollieSubscriptionId,
            'nextPaymentAt' => $nextPaymentAt,
            'canceledAt' => null,
            'historyEntries' => [$subscriptionHistories]
        ];

        $this->subscriptionRepository->upsert([$upsertData], $context);
        $this->logger->info('Subscription skipped', $logData);

        return $mollieSubscription;
    }
}
