<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Event;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Shopware\Core\Framework\Context;

final class ModifyCreatePaymentPayloadEvent
{
    public function __construct(private CreatePayment $payment, private Context $context)
    {
    }

    public function getPayment(): CreatePayment
    {
        return $this->payment;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
