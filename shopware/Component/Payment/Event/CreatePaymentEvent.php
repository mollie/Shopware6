<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Event;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class CreatePaymentEvent
{
    public function __construct(private CreatePayment $payment, private SalesChannelContext $salesChannelContext)
    {
    }

    public function getPayment(): CreatePayment
    {
        return $this->payment;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
