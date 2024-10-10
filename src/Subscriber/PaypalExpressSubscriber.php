<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaypalExpressSubscriber implements EventSubscriberInterface
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var PayPalExpress
     */
    private $paypal;


    /**
     * @param SettingsService $settingsService
     * @param PayPalExpress $paypal
     */
    public function __construct(SettingsService $settingsService, PayPalExpress $paypal)
    {
        $this->settingsService = $settingsService;
        $this->paypal = $paypal;
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

        $paymentEnabled = $this->paypal->isPaypalExpressEnabled($event->getSalesChannelContext());

        $event->setParameter('mollie_paypalexpress_enabled', $paymentEnabled);

        $style = $settings->getPaypalExpressButtonStyle();
        $shape = $settings->getPaypalExpressButtonShape();

        $restrictions = $settings->getPaypalExpressRestrictions();

        $event->setParameter('mollie_paypalexpress_style', $style);
        $event->setParameter('mollie_paypalexpress_shape', $shape);
        $event->setParameter('mollie_paypalexpress_restrictions', $restrictions);
    }
}
