<?php

namespace Kiener\MolliePayments\Struct\PaymentMethod;


use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

class PaymentMethodAttributes
{

    /**
     * @var string
     */
    private $handlerIdentifier;


    /**
     * @param PaymentMethodEntity $paymentMethod
     */
    public function __construct(PaymentMethodEntity $paymentMethod)
    {
        $this->handlerIdentifier = (string)$paymentMethod->getHandlerIdentifier();
    }

    /**
     * @return bool
     */
    public function isVoucherMethod(): bool
    {
        return $this->handlerIdentifier === VoucherPayment::class;
    }

}