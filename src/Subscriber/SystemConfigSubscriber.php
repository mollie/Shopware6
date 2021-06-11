<?php

namespace Kiener\MolliePayments\Subscriber;

use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event)
    {
        if(!in_array($event->getKey(), [
            'MolliePayments.config.liveApiKey',
            'MolliePayments.config.testApiKey',
        ])) {
            return;
        }

        dd($event);
    }
}
