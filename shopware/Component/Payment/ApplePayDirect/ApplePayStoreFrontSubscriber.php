<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractApplePayDirectEnabledRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\ApplePayDirectEnabledRoute;
use Mollie\Shopware\Component\Payment\ExpressMethod\AbstractCartBackupService;
use Mollie\Shopware\Component\Payment\ExpressMethod\CartBackupService;
use Mollie\Shopware\Component\Payment\Method\ApplePayPayment;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Entity\Order\MollieShopwareOrder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ApplePayStoreFrontSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
        #[Autowire(service: ApplePayDirectEnabledRoute::class)]
        private AbstractApplePayDirectEnabledRoute $applePayDirectEnabledRoute,
        #[Autowire(service: CartBackupService::class)]
        private AbstractCartBackupService $cartBackupService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
            CheckoutFinishPageLoadedEvent::class => 'onRestoreBackup',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannel = $salesChannelContext->getSalesChannel();
        $salesChannelId = $salesChannel->getId();

        try {
            $isNotLoggedIn = $salesChannelContext->getCustomer() === null;
            $accountSettings = $this->settings->getAccountSettings($salesChannelId);
            $event->setParameter('mollie_express_required_data_protection', $isNotLoggedIn && $accountSettings->isDataProtectionEnabled());

            $response = $this->applePayDirectEnabledRoute->getEnabled($salesChannelContext);
            $applePayMethodId = $response->getPaymentMethodId();

            if ($applePayMethodId === null) {
                return;
            }

            $applePaySettings = $this->settings->getApplePaySettings($salesChannelId);
            $shoPhoneNumberField = $accountSettings->isPhoneFieldShown() || $accountSettings->isPhoneFieldRequired();

            $event->setParameter('apple_pay_payment_method_id', $applePayMethodId);
            $event->setParameter('mollie_applepay_enabled', true);
            $event->setParameter('mollie_applepaydirect_phonenumber_required', (int) $shoPhoneNumberField);
            $event->setParameter('mollie_applepaydirect_enabled', $response->isEnabled());
            $event->setParameter('mollie_applepaydirect_restrictions', $applePaySettings->getVisibilityRestrictions()->toArray());
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to assign apple pay direct data to storefront', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);
        }
    }

    public function onRestoreBackup(CheckoutFinishPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $mollieOrder = new MollieShopwareOrder($event->getPage()->getOrder());
        $latestTransaction = $mollieOrder->getLatestTransaction();

        if ($latestTransaction === null) {
            return;
        }

        $paymentMethod = $latestTransaction->getPaymentMethod();

        if (! $paymentMethod instanceof PaymentMethodEntity) {
            return;
        }

        if ($paymentMethod->getHandlerIdentifier() !== ApplePayPayment::class) {
            return;
        }

        try {
            $this->cartBackupService->restoreCart($context);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to restore cart after apple pay direct checkout', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
