<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress;

use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PayPalExpressStoreFrontSubscriber implements EventSubscriberInterface
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
            $paypalExpressMethodId = $this->paymentMethodRepository->getIdByPaymentHandler(PayPalExpressPayment::class, $salesChannelId,$salesChannelContext->getContext());
            if ($paypalExpressMethodId === null) {
                return;
            }
            $paypalExpressSettings = $this->settings->getPaypalExpressSettings();

            $event->setParameter('mollie_paypalexpress_enabled', $paypalExpressSettings->isEnabled());

            $event->setParameter('mollie_paypalexpress_style', $paypalExpressSettings->getStyle());
            $event->setParameter('mollie_paypalexpress_shape', $paypalExpressSettings->getShape());
            $event->setParameter('mollie_paypalexpress_restrictions', $paypalExpressSettings->getRestrictions());
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to assign paypal express data to storefront', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);
        }
    }
}
