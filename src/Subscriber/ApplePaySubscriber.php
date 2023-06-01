<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApplePaySubscriber implements EventSubscriberInterface
{
    /**
     * @var ApplePayDirect
     */
    private $applePay;


    /**
     * @param ApplePayDirect $applePay
     */
    public function __construct(ApplePayDirect $applePay)
    {
        $this->applePay = $applePay;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    /**
     * @param StorefrontRenderEvent $event
     * @throws \Exception
     * @return void
     */
    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        try {
            $applePayEnabled = $this->applePay->getActiveApplePayID($event->getSalesChannelContext());
        } catch (\Exception $ex) {
            $applePayEnabled = false;
        }

        $event->setParameter('mollie_applepay_enabled', $applePayEnabled);
    }
}
