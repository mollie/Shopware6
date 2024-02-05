<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomerServiceInterface;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
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
     * @var CustomerServiceInterface
     */
    private $customerService;

    /**
     * @param SettingsService $settingsService
     * @param PayPalExpress $paypal
     * @param CustomerServiceInterface $customerService
     */
    public function __construct(SettingsService $settingsService, PayPalExpress $paypal, CustomerServiceInterface $customerService)
    {
        $this->settingsService = $settingsService;
        $this->paypal = $paypal;
        $this->customerService = $customerService;
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
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());

        $paymentEnabled = $this->paypal->isPaypalExpressEnabled($event->getSalesChannelContext());

        $event->setParameter('mollie_paypalexpress_enabled', $paymentEnabled);

        $style = $settings->getPaypalExpressButtonStyle();
        $shape = $settings->getPaypalExpressButtonShape();

        $restrictions = $settings->getPaypalExpressRestrictions();

        $isPPE = false;
        if ($paymentEnabled) {
            $customer = $this->customerService->getCustomer(
                (string)$event->getSalesChannelContext()->getCustomerId(),
                $event->getContext()
            );

            if ($customer instanceof CustomerEntity) {
                $customFields = $customer->getCustomFields();
                if ($customFields !== null && isset($customFields['mollie_payments'])) {
                    $isPPE = (bool)$customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['ppe_session_id'];
                }
            }
        }

        $event->setParameter('mollie_paypalexpress_used', $isPPE);
        $event->setParameter('mollie_paypalexpress_style', $style);
        $event->setParameter('mollie_paypalexpress_shape', $shape);
        $event->setParameter('mollie_paypalexpress_restrictions', $restrictions);

    }
}
