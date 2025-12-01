<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class AlmaPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::ALMA;
    }

    public function getName(): string
    {
        return 'Alma';
    }

    public function getDescription(): string
    {
        return 'Offer a flexible and accessible payment method with Alma, France’s leading buy now, pay later solution. Consumers prefer Alma as it allows them to defer payments and provides more financial flexibility.';
    }
}
