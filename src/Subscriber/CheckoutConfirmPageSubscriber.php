<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Service\MandateServiceInterface;
use Kiener\MolliePayments\Service\MollieLocaleService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    /**
     * @var MollieApiFactory
     */
    private $apiFactory;

    /**
     * @var MollieApiClient
     */
    private $apiClient;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var MollieSettingStruct
     */
    private $settings;

    /**
     * @var MollieLocaleService
     */
    private $mollieLocaleService;

    /**
     * @var MandateServiceInterface
     */
    private $mandateService;

    /**
     * @var MollieGatewayInterface
     */
    private $mollieGateway;

    /**
     * @var ?string
     */
    private $profileId;

    public function __construct(MollieApiFactory $apiFactory, SettingsService $settingsService, MandateServiceInterface $mandateService, MollieGatewayInterface $mollieGateway, MollieLocaleService $mollieLocaleService)
    {
        $this->apiFactory = $apiFactory;
        $this->settingsService = $settingsService;
        $this->mandateService = $mandateService;
        $this->mollieGateway = $mollieGateway;
        $this->mollieLocaleService = $mollieLocaleService;
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => [
                ['addDataToPage', 10],
            ],
            AccountEditOrderPageLoadedEvent::class => ['addDataToPage', 10],
        ];
    }

    /**
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     *
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function addDataToPage($args): void
    {
        $scId = $args->getSalesChannelContext()->getSalesChannel()->getId();

        $currentSelectedPaymentMethod = $args->getSalesChannelContext()->getPaymentMethod();

        $mollieAttributes = new PaymentMethodAttributes($currentSelectedPaymentMethod);

        // load additional data only for mollie payment methods
        if (! $mollieAttributes->isMolliePayment()) {
            return;
        }

        // load our settings for the
        // current request
        $this->settings = $this->settingsService->getSettings($scId);

        // now use our factory to get the correct
        // client with the correct sales channel settings
        $this->apiClient = $this->apiFactory->getClient($scId);

        $this->mollieGateway->switchClient($scId);

        $this->addMollieLocaleVariableToPage($args);
        $this->addMollieProfileIdVariableToPage($args);
        $this->addMollieTestModeVariableToPage($args);
        $this->addMollieComponentsVariableToPage($args);
        $this->addMollieSingleClickPaymentDataToPage($args, $mollieAttributes);
        $this->addMolliePosTerminalsVariableToPage($args, $mollieAttributes);
    }

    /**
     * Adds the locale for Mollie components to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    private function addMollieLocaleVariableToPage($args): void
    {
        $salesChannelContext = $args->getSalesChannelContext();

        $locale = $this->mollieLocaleService->getLocale($salesChannelContext);

        $args->getPage()->assign([
            'mollie_locale' => $locale,
        ]);
    }

    /**
     * Adds the test mode variable to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    private function addMollieTestModeVariableToPage($args): void
    {
        $args->getPage()->assign([
            'mollie_test_mode' => $this->settings->isTestMode() ? 'true' : 'false',
        ]);
    }

    /**
     * Adds the profile id to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    private function addMollieProfileIdVariableToPage($args): void
    {
        $mollieProfileId = $this->loadMollieProfileId();

        $args->getPage()->assign([
            'mollie_profile_id' => $mollieProfileId,
        ]);
    }

    private function loadMollieProfileId(): string
    {
        if ($this->profileId !== null) {
            return $this->profileId;
        }
        $mollieProfileId = '';

        /*
         * Fetches the profile id from Mollie's API for the current key.
         */
        try {
            if ($this->apiClient->usesOAuth() === false) {
                $mollieProfile = $this->apiClient->profiles->get('me');
            } else {
                $mollieProfile = $this->apiClient->profiles->page()->offsetGet(0);
            }

            if (isset($mollieProfile->id)) {
                $mollieProfileId = $mollieProfile->id;
            }
        } catch (ApiException $e) {
        }
        $this->profileId = $mollieProfileId;

        return $this->profileId;
    }

    /**
     * Adds the components variable to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    private function addMollieComponentsVariableToPage($args): void
    {
        $args->getPage()->assign([
            'enable_credit_card_components' => $this->settings->getEnableCreditCardComponents(),
            'enable_one_click_payments_compact_view' => $this->settings->isOneClickPaymentsCompactView(),
        ]);
    }

    /**
     * Adds ideal issuers variable to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     * @param PaymentMethodAttributes $selectedPayment
     */
    private function addMolliePosTerminalsVariableToPage($args, $selectedPayment): void
    {
        // do not load terminals if not required
        if ($selectedPayment->getMollieIdentifier() !== PaymentMethod::POINT_OF_SALE) {
            return;
        }
        try {
            $terminalsArray = [];

            $terminals = $this->mollieGateway->getPosTerminals();

            foreach ($terminals as $terminal) {
                $terminalsArray[] = [
                    'id' => $terminal->id,
                    'name' => $terminal->description,
                ];
            }

            $args->getPage()->assign(
                [
                    'mollie_terminals' => $terminalsArray,
                ]
            );
        } catch (\Exception $e) {
        }
    }

    /**
     * Adds the components variable to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     * @param PaymentMethodAttributes $selectedPayment
     */
    private function addMollieSingleClickPaymentDataToPage($args, $selectedPayment): void
    {
        // do not load credit card mandate if not required
        if ($selectedPayment->getMollieIdentifier() !== PaymentMethod::CREDITCARD) {
            return;
        }
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

            // only load the list of mandates if the payment method is CreditCardPayment
            if ($salesChannelContext->getPaymentMethod()->getHandlerIdentifier() !== CreditCardPayment::class) {
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
