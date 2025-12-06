<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\PaymentMethod;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

class PaymentMethodAttributes
{
    public const MOLLIE_PAYMENT_HANDLER_NAMESPACE = 'Mollie\Shopware\Component\Payment\Method';

    /**
     * @var string
     */
    private $handlerIdentifier;

    public function __construct(PaymentMethodEntity $paymentMethod)
    {
        $this->handlerIdentifier = (string) $paymentMethod->getHandlerIdentifier();
    }

    public function isMolliePayment(): bool
    {
        $pattern = sprintf(
            '/^%s/',
            preg_quote(self::MOLLIE_PAYMENT_HANDLER_NAMESPACE)
        );

        return preg_match($pattern, $this->handlerIdentifier) === 1;
    }

    public function getMollieIdentifier(): string
    {
        if (! class_exists($this->handlerIdentifier)
            || ! defined("{$this->handlerIdentifier}::PAYMENT_METHOD_NAME")) {
            return '-';
        }

        return constant($this->handlerIdentifier . '::PAYMENT_METHOD_NAME') ?? '';
    }
}
