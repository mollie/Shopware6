<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Enricher;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEvents;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class LiveDataEnricher implements EventSubscriberInterface
{
    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;


    /**
     * @param MollieGatewayInterface $gwMollie
     */
    public function __construct(MollieGatewayInterface $gwMollie)
    {
        $this->gwMollie = $gwMollie;
    }


    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SubscriptionEvents::SUBSCRIPTIONS_LOADED_EVENT => 'onSubscriptionsLoaded'
        ];
    }

    /**
     * @param EntityLoadedEvent $event
     */
    public function onSubscriptionsLoaded(EntityLoadedEvent $event): void
    {
        /** @var SubscriptionEntity $subscription */
        foreach ($event->getEntities() as $subscription) {

            try {

                $this->gwMollie->switchClient($subscription->getSalesChannelId());

                $mollieSubscription = $this->gwMollie->getSubscription($subscription->getMollieId(), $subscription->getMollieCustomerId());

                $subscription->setMollieStatus($mollieSubscription->status);

            } catch (\Throwable $ex) {
                # todo
            }
        }
    }
}

