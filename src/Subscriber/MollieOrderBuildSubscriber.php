<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Event\MollieOrderBuildEvent;

class MollieOrderBuildSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface
{
    public const METADATA_SHORT_TRANSACTION_ID_KEY = 'tid';

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            MollieOrderBuildEvent::class => 'onMollieOrderBuilt'
        ];
    }

    /**
     * Adds the first
     * @param MollieOrderBuildEvent $event
     * @return void
     */
    public function onMollieOrderBuilt(MollieOrderBuildEvent $event)
    {
        $transactionId = $event->getTransactionId();

        if (!empty($transactionId)) {
            $shortId = substr($transactionId, 0, 8);
            $event->setMetadata([self::METADATA_SHORT_TRANSACTION_ID_KEY =>$shortId]);
        }
    }
}
