<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event\Payment;

use Mollie\Shopware\Component\FlowBuilder\Event\BaseEvent;

final class FailedEvent extends BaseEvent
{
    public const EVENT_NAME = 'mollie.payment.failed';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
