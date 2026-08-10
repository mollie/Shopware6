<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Shopware\Core\Framework\Context;

interface OrderReturnHandlerInterface
{
    public function return(string $returnId, Context $context): void;

    public function cancel(string $returnId, Context $context): void;

    public function returnOnCreatedAsDone(string $returnId, Context $context): void;
}
