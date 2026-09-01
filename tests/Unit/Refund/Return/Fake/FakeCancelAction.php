<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Return\Fake;

use Mollie\Shopware\Component\Refund\Return\CancelAction;
use Shopware\Core\Framework\Context;

/**
 * See FakeRefundAction for why the constructor is empty.
 */
final class FakeCancelAction extends CancelAction
{
    /** @var string[] */
    public array $executeCalls = [];

    public function __construct()
    {
    }

    public function execute(string $returnId, Context $context): void
    {
        $this->executeCalls[] = $returnId;
    }
}
