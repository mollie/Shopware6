<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\MandateServiceInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MandatesSubscriber implements EventSubscriberInterface
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var MollieSettingStruct
     */
    private $settings;

    /**
     * @var MandateServiceInterface
     */
    private $mandateService;

    public function __construct(
        SettingsService $settingsService,
        MandateServiceInterface $mandateService
    ) {
        $this->settingsService = $settingsService;
        $this->mandateService = $mandateService;
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => ['addDataToPage', 10],
        ];
    }

    public function addDataToPage(StorefrontRenderEvent $args): void
    {
        // load our settings for the
        // current request
        $this->settings = $this->settingsService->getSettings($args->getSalesChannelContext()->getSalesChannelId());

        $this->addMollieSingleClickPaymentDataToPage($args);
    }

    /**
     * Adds the components variable to the storefront.
     */
    private function addMollieSingleClickPaymentDataToPage(StorefrontRenderEvent $args): void
    {
        $args->setParameter('enable_one_click_payments', $this->settings->isOneClickPaymentsEnabled());

        if (! $this->settings->isOneClickPaymentsEnabled()) {
            return;
        }

        try {
            $salesChannelContext = $args->getSalesChannelContext();
            $loggedInCustomer = $salesChannelContext->getCustomer();
            if (! $loggedInCustomer instanceof CustomerEntity) {
                return;
            }

            $mandates = $this->mandateService->getCreditCardMandatesByCustomerId($loggedInCustomer->getId(), $salesChannelContext);

            $args->setParameter('MollieCreditCardMandateCollection', $mandates);
        } catch (\Exception $e) {
        }
    }
}
