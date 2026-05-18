<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Payment\Action\FinalizeInterface;
use Mollie\Shopware\Component\Payment\Transaction\MollieTransactionStruct;
use Shopware\Core\Framework\Context;

final class FakeFinalize implements FinalizeInterface
{
    public function execute(MollieTransactionStruct $transaction, Context $context): void
    {
    }
}
