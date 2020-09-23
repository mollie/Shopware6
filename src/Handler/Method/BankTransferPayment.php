<?php

namespace Kiener\MolliePayments\Handler\Method;

use Exception;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BankTransferPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::BANKTRANSFER;
    public const PAYMENT_METHOD_DESCRIPTION = 'Banktransfer';
    public const DUE_DATE_MIN_DAYS = 1;
    public const DUE_DATE_MAX_DAYS = 100;

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    /**
     * @param array               $orderData
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity      $customer
     * @param LocaleEntity        $locale
     *
     * @return array
     */
    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        if (!array_key_exists(static::FIELD_BILLING_EMAIL, $orderData[static::FIELD_PAYMENT]) || in_array($orderData[static::FIELD_PAYMENT][static::FIELD_BILLING_EMAIL], [null, ''], true)) {
            $orderData[static::FIELD_PAYMENT][static::FIELD_BILLING_EMAIL] = $customer->getEmail();
        }

        if (!array_key_exists(static::FIELD_DUE_DATE, $orderData[static::FIELD_PAYMENT]) || in_array($orderData[static::FIELD_PAYMENT][static::FIELD_DUE_DATE], [null, ''], true)) {
            /** @var MollieSettingStruct $settings */
            $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());

            try {
                $dueDate = $settings->getPaymentMethodBankTransferDueDate();
                $orderData[static::FIELD_PAYMENT][static::FIELD_DUE_DATE] = $dueDate;
            } catch (Exception $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $salesChannelContext->getContext(),
                    $e,
                    [
                        'function' => 'finalize-payment',
                    ]
                );
            }
        }

        if (!array_key_exists(static::FIELD_LOCALE, $orderData[static::FIELD_PAYMENT]) || in_array($orderData[static::FIELD_PAYMENT][static::FIELD_LOCALE], [null, ''], true)) {
            $orderData[static::FIELD_PAYMENT][static::FIELD_LOCALE] = $locale->getCode();
        }

        return $orderData;
    }
}