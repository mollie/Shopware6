<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Shopware\Core\Checkout\Order\OrderEntity;

final class PosPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::POS;
    }

    public function getName(): string
    {
        return 'POS Terminal';
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment, OrderEntity $orderEntity): CreatePayment
    {
        // TODO terminalId
        return $payment;
    }
}
