<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Repository\PaymentMethodRepository;
use Mollie\Shopware\Repository\PaymentMethodRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ApplePayStoreFrontSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
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
            $applePayMethodId = $this->paymentMethodRepository->getIdForPaymentMethod(PaymentMethod::APPLEPAY, $salesChannelId, $salesChannelContext->getContext());

            if ($applePayMethodId === null) {
                return;
            }
            $accountSettings = $this->settings->getAccountSettings($salesChannelId);
            $applePaySettings = $this->settings->getApplePaySettings($salesChannelId);
            $shoPhoneNumberField = $accountSettings->isPhoneFieldShown() || $accountSettings->isPhoneFieldRequired();
            $isNotLoggedIn = $salesChannelContext->getCustomer() === null;

            $event->setParameter('apple_pay_payment_method_id', $applePayMethodId);
            $event->setParameter('mollie_applepaydirect_phonenumber_required', (int) $shoPhoneNumberField);
            $event->setParameter('mollie_applepaydirect_enabled', $applePaySettings->isApplePayDirectEnabled());
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
