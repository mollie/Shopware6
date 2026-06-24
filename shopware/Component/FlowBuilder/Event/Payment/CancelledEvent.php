<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event\Payment;

final class CancelledEvent extends AbstractPaymentEvent
{
    public const EVENT_NAME = 'mollie.payment.cancelled';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
