<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event\Payment;

final class FailedEvent extends AbstractPaymentEvent
{
    public const EVENT_NAME = 'mollie.payment.failed';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
