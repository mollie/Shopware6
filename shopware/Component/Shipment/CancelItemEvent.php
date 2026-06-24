<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Shopware\Core\Framework\Context;

final class CancelItemEvent
{
    public function __construct(private string $transactionId, private Context $context)
    {
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
