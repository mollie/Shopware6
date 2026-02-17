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
use Mollie\Shopware\Component\Subscription\Action\Exception\PauseAndResumeNotAllowedException;
use Mollie\Shopware\Component\Subscription\Action\Exception\SubscriptionActiveException;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionResumedEvent;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ResumeAction extends AbstractAction
{
    private const ACTION_NAME = 'resume';

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     * @param SubscriptionGateway $subscriptionGateway
     */
    public function __construct(
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getActioName(): string
    {
        return self::ACTION_NAME;
    }

    public function getEventClass(): string
    {
        return SubscriptionResumedEvent::class;
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

        $this->logger->info('Starting to resume subscription', $logData);

        if (! $settings->isAllowPauseAndResume()) {
            $this->logger->error('Resuming subscriptions is disabled in the settings', $logData);
            throw new PauseAndResumeNotAllowedException($salesChannelId);
        }

        if ($mollieSubscriptionStatus->isActive()) {
            $this->logger->error('Mollie subscription status is active', $logData);
            throw new SubscriptionActiveException($subscriptionId);
        }

        $metaData = $subscription->getMetadata();

        $startDate = max($metaData->getNextPossiblePaymentDate(), new \DateTime());

        $mollieSubscription->setStartDate($startDate);

        $newSubscription = $this->subscriptionGateway->copySubscription($mollieSubscription, $mollieCustomerId, $orderNumber, $salesChannelId);
        $mollieSubscriptionId = $newSubscription->getId();

        $logData['newMollieSubscriptionId'] = $mollieSubscriptionId;

        $newStatus = SubscriptionStatus::RESUMED;

        $subscriptionHistories = [
            'statusFrom' => $shopwareSubscriptionStatus->value,
            'statusTo' => $newStatus->value,
            'mollieId' => $mollieSubscriptionId,
            'comment' => 'resumed'
        ];

        $upsertData = [
            'id' => $subscriptionId,
            'status' => $newStatus->value,
            'mollieId' => $mollieSubscriptionId,
            'nextPaymentAt' => $newSubscription->getNextPaymentDate(),
            'canceledAt' => null,
            'historyEntries' => [$subscriptionHistories]
        ];

        $this->subscriptionRepository->upsert([$upsertData], $context);
        $this->logger->info('Subscription resumed', $logData);

        return $newSubscription;
    }
}
