<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Event;

use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Framework\Context;

final class PaymentFinalizeEvent
{
    public function __construct(private Payment $payment, private Context $context)
    {
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
