<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Shopware\Core\Checkout\Order\OrderEntity;

final class KlarnaPayment extends AbstractMolliePaymentHandler
{
    protected string $method = 'klarna';

    public function applyPaymentSpecificParameters(CreatePayment $payment, OrderEntity $orderEntity): CreatePayment
    {
        $payment->setCaptureMode(new CaptureMode(CaptureMode::MANUAL));

        return $payment;
    }
}
