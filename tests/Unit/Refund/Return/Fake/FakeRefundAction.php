<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Return\Fake;

use Mollie\Shopware\Component\Refund\Return\RefundAction;
use Shopware\Core\Framework\Context;

/**
 * The real action is reached through an abstract parent with promoted dependencies. None of them is
 * touched here, so an empty constructor is enough to leave them unset.
 */
final class FakeRefundAction extends RefundAction
{
    /** @var string[] */
    public array $executeCalls = [];

    /** @var string[] */
    public array $executeOnCreateCalls = [];

    public function __construct()
    {
    }

    public function execute(string $returnId, Context $context): void
    {
        $this->executeCalls[] = $returnId;
    }

    public function executeOnCreate(string $returnId, Context $context): void
    {
        $this->executeOnCreateCalls[] = $returnId;
    }
}
