<?php

namespace Kiener\MolliePayments\Struct\PaymentMethod;


use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

class PaymentMethodAttributes
{

    /**
     * @var string
     */
    private $molliePaymentName;


    public function __construct(PaymentMethodEntity $paymentMethod)
    {
        $this->molliePaymentName = '';

        $customFields = $paymentMethod->getCustomFields();

        if ($customFields === null) {
            return;
        }

        if (array_key_exists('mollie_payment_method_name', $customFields)) {
            $this->molliePaymentName = (string)$customFields['mollie_payment_method_name'];
        }
    }

    /**
     * @return string
     */
    public function getMolliePaymentName(): string
    {
        return $this->molliePaymentName;
    }

}