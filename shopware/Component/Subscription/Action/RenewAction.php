<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionEndedEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionRenewedEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandlerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RenewAction
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: SubscriptionActionHandler::class)]
        private readonly SubscriptionActionHandlerInterface $subscriptionActionHandler,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(
        SubscriptionEntity $subscription,
        Subscription $mollieSubscription,
        Payment $molliePayment,
        SubscriptionStatus $previousStatus,
        CustomerEntity $customer,
        ?string $afterRenewalAction,
        Context $context
    ): void {
        $subscriptionId = $subscription->getId();
        $historyEntries = [];
        $statusBeforeRenewed = $previousStatus;

        if ($previousStatus->isInterrupted()) {
            $historyEntries[] = [
                'statusFrom' => $previousStatus->value,
                'statusTo' => SubscriptionStatus::RESUMED->value,
                'mollieId' => $mollieSubscription->getId(),
                'comment' => 'resumed',
            ];
            $statusBeforeRenewed = SubscriptionStatus::RESUMED;
        }

        $today = new \DateTime();
        $nextPaymentDate = $mollieSubscription->getNextPaymentDate() ?? $today;
        $nextPaymentDate = max($nextPaymentDate, $today);

        $historyEntries[] = [
            'statusFrom' => $statusBeforeRenewed->value,
            'statusTo' => SubscriptionStatus::ACTIVE->value,
            'mollieId' => $mollieSubscription->getId(),
            'comment' => 'renewed',
        ];

        $this->subscriptionRepository->upsert([[
            'id' => $subscriptionId,
            'mandateId' => (string) $molliePayment->getMandateId(),
            'nextPaymentAt' => $nextPaymentDate->format('Y-m-d'),
            'historyEntries' => $historyEntries,
        ]], $context);

        $this->eventDispatcher->dispatch(new SubscriptionRenewedEvent($subscription, $customer, $context));

        if ($mollieSubscription->getStatus() === SubscriptionStatus::COMPLETED) {
            $this->eventDispatcher->dispatch(new SubscriptionEndedEvent($subscription, $customer, $context));
        }

        if ($afterRenewalAction === null) {
            return;
        }

        try {
            $this->subscriptionActionHandler->handle($afterRenewalAction, $subscriptionId, $context);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to execute after renewal action: ' . $afterRenewalAction, [
                'subscriptionId' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
