<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\DirectDebitPayment;
use Kiener\MolliePayments\Handler\Method\EpsPayment;
use Kiener\MolliePayments\Handler\Method\GiftCardPayment;
use Kiener\MolliePayments\Handler\Method\GiroPayPayment;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Handler\Method\IngHomePayPayment;
use Kiener\MolliePayments\Handler\Method\KbcPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayLaterPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaSliceItPayment;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\Method\PaySafeCardPayment;
use Kiener\MolliePayments\Handler\Method\Przelewy24Payment;
use Kiener\MolliePayments\Handler\Method\SofortPayment;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class PaymentMethodService
{
    /** @var EntityRepositoryInterface */
    protected $paymentRepository;

    /** @var PluginIdProvider */
    protected $pluginIdProvider;

    /** @var EntityRepositoryInterface */
    protected $systemConfigRepository;

    /** @var string */
    protected $className;

    /**
     * PaymentMethodHelper constructor.
     *
     * @param EntityRepositoryInterface $paymentRepository
     * @param PluginIdProvider $pluginIdProvider
     * @param EntityRepositoryInterface $systemConfigRepository
     * @param null $className
     */
    public function __construct(
        EntityRepositoryInterface $paymentRepository,
        PluginIdProvider $pluginIdProvider,
        EntityRepositoryInterface $systemConfigRepository,
        $className = null)
    {
        $this->paymentRepository = $paymentRepository;
        $this->pluginIdProvider = $pluginIdProvider;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->className = $className;
    }

    /**
     * @param Context $context
     */
    public function addPaymentMethods(Context $context) : void
    {
        // Get the plugin ID
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass($this->className, $context);

        // Variables
        $paymentData = [];
        $paymentMethods = null;

        try {
            $paymentMethods = $this->getPaymentMethods($context);
        } catch (IncompatiblePlatform $e) {
            // @todo Handle IncompatiblePlatform exception
        }

        foreach ($paymentMethods as $paymentMethod) {
            // Get the PaymentHandler class
            $paymentHandlerClass = $this->getPaymentHandlerClass($paymentMethod->id);

            if ($paymentHandlerClass === null) {
                continue;
            }

            // Build array of payment method data
            $paymentMethodData = [
                'handlerIdentifier' => $paymentHandlerClass,
                'name' => $paymentMethod->description,
                'pluginId' => $pluginId,
                'active' => $paymentMethod->active ?? false,
                'customFields' => [
                    'mollie_payment_method_name' => $paymentMethod->id
                ]
            ];

            // Get existing payment method so we can update it by it's ID
            try {
                $existingPaymentMethodId = $this->getPaymentMethodId(
                    $paymentMethodData['handlerIdentifier'],
                    $paymentMethodData['name']
                );
            } catch (InconsistentCriteriaIdsException $e) {
                // On error, we assume the payment method doesn't exist
            }

            if ($existingPaymentMethodId !== null) {
                $paymentMethodData['id'] = $existingPaymentMethodId;
            }

            // Add payment method data to array of payment data
            $paymentData[] = $paymentMethodData;
        }

        // Insert or update payment data
        if (count($paymentData)) {
            $this->paymentRepository->upsert($paymentData, $context);
        }
    }

    /**
     * @param $paymentMethod
     * @return string|null
     */
    public function getPaymentHandlerClass($paymentMethod)
    {
        // Return Apple Pay PaymentHandler
        if ($paymentMethod === PaymentMethod::APPLEPAY) {
            return ApplePayPayment::class;
        }

        // Return BanContact PaymentHandler
        if ($paymentMethod === PaymentMethod::BANCONTACT) {
            return BanContactPayment::class;
        }

        // Return Bank Transfer PaymentHandler
        if ($paymentMethod === PaymentMethod::BANKTRANSFER) {
            return BankTransferPayment::class;
        }

        // Return Credit Card PaymentHandler
        if ($paymentMethod === PaymentMethod::CREDITCARD) {
            return CreditCardPayment::class;
        }

        // Return Direct Debit PaymentHandler
        if ($paymentMethod === PaymentMethod::DIRECTDEBIT) {
            return DirectDebitPayment::class;
        }

        // Return EPS PaymentHandler
        if ($paymentMethod === PaymentMethod::EPS) {
            return EpsPayment::class;
        }

        // Return Gift Card PaymentHandler
        if ($paymentMethod === PaymentMethod::GIFTCARD) {
            return GiftCardPayment::class;
        }

        // Return GiroPay PaymentHandler
        if ($paymentMethod === PaymentMethod::GIROPAY) {
            return GiroPayPayment::class;
        }

        // Return iDeal PaymentHandler
        if ($paymentMethod === PaymentMethod::IDEAL) {
            return iDealPayment::class;
        }

        // Return ING HomePay PaymentHandler
        if ($paymentMethod === PaymentMethod::INGHOMEPAY) {
            return IngHomePayPayment::class;
        }

        // Return KBC PaymentHandler
        if ($paymentMethod === PaymentMethod::KBC) {
            return KbcPayment::class;
        }

        // Return Klarna Pay Later PaymentHandler
        if ($paymentMethod === PaymentMethod::KLARNA_PAY_LATER) {
            return KlarnaPayLaterPayment::class;
        }

        // Return Klarna Slice It PaymentHandler
        if ($paymentMethod === PaymentMethod::KLARNA_SLICE_IT) {
            return KlarnaSliceItPayment::class;
        }

        // Return PayPal PaymentHandler
        if ($paymentMethod === PaymentMethod::PAYPAL) {
            return PayPalPayment::class;
        }

        // Return PaySafeCard PaymentHandler
        if ($paymentMethod === PaymentMethod::PAYSAFECARD) {
            return PaySafeCardPayment::class;
        }

        // Return Prezelewy24 PaymentHandler
        if ($paymentMethod === PaymentMethod::PRZELEWY24) {
            return Przelewy24Payment::class;
        }

        // Return SOFORT PaymentHandler
        if ($paymentMethod === PaymentMethod::SOFORT) {
            return SofortPayment::class;
        }

        return null;
    }

    /**
     * Get payment method by name.
     *
     * @param $name
     * @return string|null
     * @throws InconsistentCriteriaIdsException
     */
    private function getPaymentMethodId($handlerIdentifier, $name) : ?string
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));
        $paymentCriteria->addFilter(new EqualsFilter('name', $name));

        // Get payment IDs
        $paymentIds = $this->paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds()[0];
    }

    /**
     * Get an array of available payment methods from the Mollie API.
     *
     * @param Context|null $context
     * @return array
     * @throws IncompatiblePlatform
     */
    private function getPaymentMethods(?Context $context = null) : array
    {
        // Variables
        $paymentMethods = [];
        $availableMethods = null;
        $activeMethods = null;

        /** @var \Mollie\Api\MollieApiClient $apiClient */
        $apiClient = $this->getApiClient($context);

        if ($apiClient === null) {
            return $paymentMethods;
        }

        // Get all available payment methods
        try {
            $availableMethods = $apiClient->methods->allAvailable();
            $activeMethods = $apiClient->methods->allActive();
        } catch (ApiException $e) {
            // @todo Handle ApiException
        }

        // Add payment methods to array
        if ($availableMethods !== null) {
            foreach ($availableMethods as $availableMethod) {
                $paymentMethods[] = $availableMethod;
            }
        }

        // Check if method is active
        if ($activeMethods !== null) {
            foreach ($paymentMethods as $paymentMethod) {
                $paymentMethod->active = false;

                foreach ($activeMethods as $activeMethod) {
                    if ($paymentMethod->id === $activeMethod->id) {
                        $paymentMethod->active = true;
                    }
                }
            }
        }

        return $paymentMethods;
    }

    /**
     * Get an instance of the Mollie API client.
     *
     * @param Context|null $context
     * @return MollieApiClient|null
     * @throws IncompatiblePlatform
     */
    private function getApiClient(?Context $context = null) : ?MollieApiClient
    {
        $client = null;

        /** @var SettingsService $settingService */
        $settingService = new SettingsService($this->systemConfigRepository);

        /** @var MollieSettingStruct $mollieSettings */
        try {
            $mollieSettings = $settingService->getSettings($context);
        } catch (InconsistentCriteriaIdsException $e) {
            // @todo Handle InconsistentCriteriaIdsException
        }

        if ($mollieSettings === null) {
            return null;
        }

        // Get API key
        $apiKey = $mollieSettings->isTestMode() === true ? $mollieSettings->getTestApiKey() : $mollieSettings->getLiveApiKey();

        // Create client
        $client = new MollieApiClient();

        try {
            $client->setApiKey($apiKey);
        } catch (ApiException $e) {
            // @todo Handle ApiException
            $client = null;
        }

        return $client;
    }
}