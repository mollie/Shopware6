<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SalesChannelContextResolvedSubscriber implements EventSubscriberInterface
{
    /** @var SettingsService */
    private $settingsService;

    public static function getSubscribedEvents()
    {
        return [
            SalesChannelContextResolvedEvent::class => 'onSalesChannelResolved'
        ];
    }

    public function __construct(SettingsService $settingsService) {
        $this->settingsService = $settingsService;
    }

    public function onSalesChannelResolved(SalesChannelContextResolvedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannelId());

        if($settings->isTestMode() === true) {
            $salesChannelContext->assign([ 'isMollieTestMode' => true ]);
        }
    }
}
