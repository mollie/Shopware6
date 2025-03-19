<?php
declare(strict_types=1);

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

    public function __construct(ApplePayDirect $applePay)
    {
        $this->applePay = $applePay;
    }

    public static function getSubscribedEvents()
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    /**
     * @throws \Exception
     */
    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        try {
            $applePayEnabled = (bool) $this->applePay->getActiveApplePayID($event->getSalesChannelContext());
        } catch (\Exception $ex) {
            $applePayEnabled = false;
        }

        $event->setParameter('mollie_applepay_enabled', $applePayEnabled);
    }
}
