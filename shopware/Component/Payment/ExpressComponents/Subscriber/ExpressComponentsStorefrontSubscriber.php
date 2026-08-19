<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Subscriber;

use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilder;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilderInterface;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Product\ProductPage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ExpressComponentsStorefrontSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
        #[Autowire(service: SessionBuilder::class)]
        private SessionBuilderInterface $sessionBuilder,
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
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        try {
            $settings = $this->settings->getExpressComponentsSettings($salesChannelId);
            if (! $settings->isEnabled()) {
                return;
            }

            $restrictions = $settings->getRestrictions()->toArray();

            $event->setParameter('mollie_express_components_enabled', true);
            $event->setParameter('mollie_express_components_restrictions', $restrictions);

            if (in_array(VisibilityRestriction::PRODUCT_DETAIL_PAGE->value, $restrictions, true)) {
                return;
            }

            $page = $event->getParameters()['page'] ?? null;

            if (! $page instanceof ProductPage) {
                return;
            }

            $session = $this->sessionBuilder->buildFromProduct($page->getProduct(), $salesChannelContext);

            $event->setParameter('mollie_express_components_session_id', $session->getId());
            $event->setParameter('mollie_express_components_client_access_token', $session->getClientAccessToken());
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to assign express components data to storefront', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);
        }
    }
}
