<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Compatibility\VersionCompare;
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
     * @throws \Exception
     * @return void
     */
    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        # we have conditional includes in TWIG to add files with the csrf function.
        # this is required to support both Shopware 6.4 and 6.5 in the storefront.
        $hasCSRF = $this->versionCompare->lt('6.5.0');

        $event->setParameter('mollie_csrf_available', $hasCSRF);
    }
}
