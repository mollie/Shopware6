<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Enricher;

use DateInterval;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEvents;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class LiveDataEnricher implements EventSubscriberInterface
{

    /**
     * @var SettingsService
     */
    private $pluginSettings;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param SettingsService $pluginSettings
     * @param MollieGatewayInterface $gwMollie
     * @param LoggerInterface $logger
     */
    public function __construct(SettingsService $pluginSettings, MollieGatewayInterface $gwMollie, LoggerInterface $logger)
    {
        $this->pluginSettings = $pluginSettings;
        $this->gwMollie = $gwMollie;
        $this->logger = $logger;
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

                # ----------------------------------------------------------------------------------------------------
                # set the cancellation until-date depending on our plugin configuration

                $settings = $this->pluginSettings->getSettings($subscription->getSalesChannelId());

                $cancellationDays = $settings->getSubscriptionsCancellationDays();

                if ($cancellationDays <= 0) {
                    # use the next payment date
                    $subscription->setCancelUntil($subscription->getNextPaymentAt());
                } else {
                    # remove x days from the next renewal date (if existing)
                    $nextPayment = $subscription->getNextPaymentAt();
                    $lastPossibleDate = null;

                    if ($nextPayment instanceof \DateTimeImmutable) {
                        $lastPossibleDate = $nextPayment->sub(new DateInterval('P' . $cancellationDays . 'D'));
                    }

                    $subscription->setCancelUntil($lastPossibleDate);
                }


                # ----------------------------------------------------------------------------------------------------
                # now also get the live status from mollie and their API

                $this->gwMollie->switchClient($subscription->getSalesChannelId());
                $mollieSubscription = $this->gwMollie->getSubscription($subscription->getMollieId(), $subscription->getMollieCustomerId());

                $subscription->setMollieStatus($mollieSubscription->status);

            } catch (\Throwable $ex) {

                $this->logger->error('Error when enriching Subscription with additional data',
                    [
                        'exception' => $ex,
                    ]
                );
            }
        }
    }
}

