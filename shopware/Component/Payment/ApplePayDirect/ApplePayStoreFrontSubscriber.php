<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractApplePayDirectEnabledRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\ApplePayDirectEnabledRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ApplePayStoreFrontSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
        #[Autowire(service: ApplePayDirectEnabledRoute::class)]
        private AbstractApplePayDirectEnabledRoute $applePayDirectEnabledRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannel = $salesChannelContext->getSalesChannel();

        $salesChannelId = $salesChannel->getId();

        try {
            $response = $this->applePayDirectEnabledRoute->getEnabled($salesChannelContext);

            $applePayMethodId = $response->getPaymentMethodId();

            if ($applePayMethodId === null) {
                return;
            }
            $accountSettings = $this->settings->getAccountSettings($salesChannelId);
            $applePaySettings = $this->settings->getApplePaySettings($salesChannelId);
            $shoPhoneNumberField = $accountSettings->isPhoneFieldShown() || $accountSettings->isPhoneFieldRequired();
            $isNotLoggedIn = $salesChannelContext->getCustomer() === null;

            $event->setParameter('apple_pay_payment_method_id', $applePayMethodId);
            $event->setParameter('mollie_applepaydirect_phonenumber_required', (int) $shoPhoneNumberField);
            $event->setParameter('mollie_applepaydirect_enabled', $response->isEnabled());

            $event->setParameter('mollie_applepaydirect_restrictions', $applePaySettings->getVisibilityRestrictions());
            $event->setParameter('mollie_express_required_data_protection', $isNotLoggedIn && $accountSettings->isDataProtectionEnabled());
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to assign apple pay direct data to storefront', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);
        }
    }
}
