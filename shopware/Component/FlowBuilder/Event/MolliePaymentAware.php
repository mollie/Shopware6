<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event;

use Mollie\Shopware\Component\Mollie\Payment;

interface MolliePaymentAware
{
    public const PAYMENT_STORAGE_KEY = 'molliePayment';

    public function getPayment(): Payment;

    public function getPaymentId(): string;
}
