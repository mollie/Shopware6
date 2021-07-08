<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    protected $extractor;

    protected $settingsService;

    public function __construct(
        MolliePaymentExtractor $extractor,
        SettingsService $settingsService
    )
    {
        $this->extractor = $extractor;
        $this->settingsService = $settingsService;
    }

    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'createCustomerAtMollie'
        ];
    }

    public function createCustomerAtMollie(CheckoutOrderPlacedEvent $event)
    {
        // Do not create a Mollie customer for guest orders.
        if($event->getOrder()->getOrderCustomer()->getCustomer()->getGuest()) {
            return;
        }

        // Do not create a customer if this order isn't being paid through Mollie.
        if(!($this->extractor->extractLast($event->getOrder()->getTransactions()) instanceof OrderTransactionEntity)) {
            return;
        }

        $settings = $this->settingsService->getSettings($event->getSalesChannelId(), $event->getContext());

        if(!$settings->createCustomersAtMollie()) {
            return;
        }
    }
}
