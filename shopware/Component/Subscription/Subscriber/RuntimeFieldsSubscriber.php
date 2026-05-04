<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Subscriber;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class RuntimeFieldsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SubscriptionEvents::SUBSCRIPTIONS_LOADED_EVENT => 'onSubscriptionsLoaded',
        ];
    }

    /**
     * @param EntityLoadedEvent<SubscriptionEntity> $event
     */
    public function onSubscriptionsLoaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $subscription) {
            $this->setCancelUntil($subscription);
        }
    }

    private function setCancelUntil(SubscriptionEntity $subscription): void
    {
        $nextPayment = $subscription->getNextPaymentAt();
        if ($nextPayment === null) {
            return;
        }

        $cancellationDays = $this->settingsService
            ->getSubscriptionSettings($subscription->getSalesChannelId())
            ->getCancelDays();

        if ($cancellationDays <= 0) {
            $subscription->setCancelUntil($nextPayment);

            return;
        }

        $subscription->setCancelUntil(
            (new \DateTimeImmutable($nextPayment->format('Y-m-d H:i:s')))
                ->sub(new \DateInterval('P' . $cancellationDays . 'D'))
        );
    }
}
