<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\MandateServiceInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountPaymentMethodPageSubscriber implements EventSubscriberInterface
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
            AccountPaymentMethodPageLoadedEvent::class => ['addDataToPage', 10],
        ];
    }

    public function addDataToPage(AccountPaymentMethodPageLoadedEvent $args): void
    {
        // load our settings for the
        // current request
        $this->settings = $this->settingsService->getSettings($args->getSalesChannelContext()->getSalesChannel()->getId());

        $this->addMollieSingleClickPaymentDataToPage($args);
    }

    /**
     * Adds the components variable to the storefront.
     */
    private function addMollieSingleClickPaymentDataToPage(AccountPaymentMethodPageLoadedEvent $args): void
    {
        $args->getPage()->assign([
            'enable_one_click_payments' => $this->settings->isOneClickPaymentsEnabled(),
        ]);

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

            $args->getPage()->setExtensions([
                'MollieCreditCardMandateCollection' => $mandates,
            ]);
        } catch (\Exception $e) {
        }
    }
}
