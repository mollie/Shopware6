<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\BelfiusPayment;
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
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
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
        $paymentMethods = $this->getPaymentMethods($context);

        foreach ($paymentMethods as $paymentMethod) {
            // Build array of payment method data
            $paymentMethodData = [
                'handlerIdentifier' => $paymentMethod['handler'],
                'name' => $paymentMethod['description'],
                'pluginId' => $pluginId,
                'customFields' => [
                    'mollie_payment_method_name' => $paymentMethod['name']
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

            if (isset($existingPaymentMethodId) && $existingPaymentMethodId !== null) {
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
     * Get payment method by ID.
     *
     * @param $id
     * @return PaymentMethodEntity
     * @throws InconsistentCriteriaIdsException
     */
    public function getPaymentMethodById($id) : ?PaymentMethodEntity
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('id', $id));

        // Get payment methods
        $paymentMethods = $this->paymentRepository->search($paymentCriteria, Context::createDefaultContext());

        if ($paymentMethods->getTotal() === 0) {
            return null;
        }

        return $paymentMethods->first();
    }

    /**
     * Get payment method ID by name.
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
     */
    private function getPaymentMethods(?Context $context = null) : array
    {
        // Variables
        $paymentMethods = [];
        $availableMethods = $this->getPaymentHandlers();

        // Add payment methods to array
        if ($availableMethods !== null) {
            foreach ($availableMethods as $availableMethod) {
                $paymentMethods[] = [
                    'name' => constant($availableMethod . '::PAYMENT_METHOD_NAME'),
                    'description' => constant($availableMethod . '::PAYMENT_METHOD_DESCRIPTION'),
                    'handler' => $availableMethod,
                ];
            }
        }

        return $paymentMethods;
    }

    /**
     * Returns an array of payment handlers.
     *
     * @return array
     */
    public function getPaymentHandlers()
    {
        return [
            ApplePayPayment::class,
            BanContactPayment::class,
            BankTransferPayment::class,
            BelfiusPayment::class,
            CreditCardPayment::class,
            DirectDebitPayment::class,
            EpsPayment::class,
            GiftCardPayment::class,
            GiroPayPayment::class,
            iDealPayment::class,
            IngHomePayPayment::class,
            KbcPayment::class,
            KlarnaPayLaterPayment::class,
            KlarnaSliceItPayment::class,
            PayPalPayment::class,
            PaySafeCardPayment::class,
            Przelewy24Payment::class,
            SofortPayment::class,
        ];
    }
}