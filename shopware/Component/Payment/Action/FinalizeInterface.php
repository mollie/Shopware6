<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Payment\Transaction\MollieTransactionStruct;
use Shopware\Core\Framework\Context;

interface FinalizeInterface
{
    public function execute(MollieTransactionStruct $transaction, Context $context): void;
}
