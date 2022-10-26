<?php

namespace Kiener\MolliePayments\Struct\PaymentMethod;

use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

class PaymentMethodAttributes
{
    public const MOLLIE_PAYMENT_HANDLER_NAMESPACE = 'Kiener\MolliePayments\Handler\Method';


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

    /**
     * @return bool
     */
    public function isMolliePayment(): bool
    {
        $pattern = sprintf(
            '/^%s/',
            preg_quote(self::MOLLIE_PAYMENT_HANDLER_NAMESPACE)
        );

        return preg_match($pattern, $this->handlerIdentifier) === 1;
    }

    /**
     * @return string
     */
    public function getMollieIdentifier(): string
    {
        if (!class_exists($this->handlerIdentifier)
            || !defined("{$this->handlerIdentifier}::PAYMENT_METHOD_NAME")) {
            return '-';
        }

        return constant($this->handlerIdentifier . '::PAYMENT_METHOD_NAME') ?? '';
    }
}
