<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Shopware\Entity\Order\MollieShopwareOrder;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
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
            CheckoutFinishPageLoadedEvent::class => 'onRestoreBackup',
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

        $shoPhoneNumberField = $settings->isPhoneNumberFieldRequired() || $settings->isPhoneNumberFieldShown();

        $applePayPaymentMethodId = "";

        try {
            $applePayPaymentMethodId = $this->applePay->getActiveApplePayID($event->getSalesChannelContext());
        } catch (\Exception $exception) {
        }

        $event->setParameter('mollie_applepaydirect_phonenumber_required', (int)$shoPhoneNumberField);
        $event->setParameter('mollie_applepaydirect_enabled', $applePayDirectEnabled);
        $event->setParameter('mollie_applepaydirect_restrictions', $settings->getRestrictApplePayDirect());
        $event->setParameter('mollie_express_required_data_protection', $settings->isRequireDataProtectionCheckbox() && $event->getSalesChannelContext()->getCustomer() === null);
        $event->setParameter('apple_pay_payment_method_id', $applePayPaymentMethodId);
    }

    /**
     * If our apple pay direct payment is done, we want to restore the original cart
     * just in case if the customer had some items in there.
     * @param CheckoutFinishPageLoadedEvent $event
     * @return void
     */
    public function onRestoreBackup(CheckoutFinishPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();

        $mollieShopwareOrder = new MollieShopwareOrder($event->getPage()->getOrder());

        $latestTransaction = $mollieShopwareOrder->getLatestTransaction();

        if (!$latestTransaction instanceof OrderTransactionEntity) {
            return;
        }

        $paymentMethod = $latestTransaction->getPaymentMethod();

        if (!$paymentMethod instanceof PaymentMethodEntity) {
            return;
        }

        $paymentIdentifier = $paymentMethod->getHandlerIdentifier();

        # Apple Pay direct will automatically restore a previous cart once the checkout is done
        # the user does not really work in the shopware checkout, and therefore it's good
        # if the cart remains the same as before the Apple Pay direct checkout
        if ($paymentIdentifier === ApplePayPayment::class) {
            $this->applePay->restoreCart($context);
        }
    }
}
