<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CsrfSubscriber implements EventSubscriberInterface
{

    /**
     * @var VersionCompare
     */
    private $versionCompare;


    /**
     * @param string $shopwareVersion
     */
    public function __construct(string $shopwareVersion)
    {
        $this->versionCompare = new VersionCompare($shopwareVersion);
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
     * @return void
     * @throws \Exception
     */
    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $hasCSRF = $this->versionCompare->lt('6.5.0');

        $event->setParameter('mollie_csrf_available', $hasCSRF);
    }
}
