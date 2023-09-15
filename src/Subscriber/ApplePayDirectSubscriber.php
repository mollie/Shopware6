<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApplePayDirectSubscriber implements EventSubscriberInterface
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var ApplePayDirect
     */
    private $applePay;


    /**
     * @param SettingsService $settingsService
     * @param ApplePayDirect $applePay
     */
    public function __construct(SettingsService $settingsService, ApplePayDirect $applePay)
    {
        $this->settingsService = $settingsService;
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
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());

        $applePayDirectEnabled = $this->applePay->isApplePayDirectEnabled($event->getSalesChannelContext());

        $event->setParameter('mollie_applepaydirect_enabled', $applePayDirectEnabled);
        $event->setParameter('mollie_applepaydirect_restrictions', $settings->getRestrictApplePayDirect());
    }
}
