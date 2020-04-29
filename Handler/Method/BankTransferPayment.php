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
        if (!array_key_exists(self::FIELD_BILLING_EMAIL, $orderData[self::FIELD_PAYMENT]) || in_array($orderData[self::FIELD_PAYMENT][self::FIELD_BILLING_EMAIL], [null, ''], true)) {
            $orderData[self::FIELD_PAYMENT][self::FIELD_BILLING_EMAIL] = $customer->getEmail();
        }

        if (!array_key_exists(self::FIELD_DUE_DATE, $orderData[self::FIELD_PAYMENT]) || in_array($orderData[self::FIELD_PAYMENT][self::FIELD_DUE_DATE], [null, ''], true)) {
            try {
                /** @var MollieSettingStruct $settings */
                $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());
                $dueDate = $settings->getPaymentMethodBankTransferDueDate();
                $orderData[self::FIELD_PAYMENT][self::FIELD_DUE_DATE] = $dueDate;
            } catch (InconsistentCriteriaIdsException $e) {
                $this->logger->error($e->getMessage(), [$e]);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage(), [$e]);
            }
        }

        if (!array_key_exists(self::FIELD_LOCALE, $orderData[self::FIELD_PAYMENT]) || in_array($orderData[self::FIELD_PAYMENT][self::FIELD_LOCALE], [null, ''], true)) {
            $orderData[self::FIELD_PAYMENT][self::FIELD_LOCALE] = $locale->getCode();
        }

        return $orderData;
    }
}