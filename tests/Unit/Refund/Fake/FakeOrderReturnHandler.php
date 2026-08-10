<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Fake;

use Mollie\Shopware\Component\Refund\OrderReturnHandlerInterface;
use Shopware\Core\Framework\Context;

final class FakeOrderReturnHandler implements OrderReturnHandlerInterface
{
    /** @var string[] */
    public array $returnCalls = [];

    /** @var string[] */
    public array $cancelCalls = [];

    /** @var string[] */
    public array $returnOnCreatedAsDoneCalls = [];

    public function return(string $returnId, Context $context): void
    {
        $this->returnCalls[] = $returnId;
    }

    public function cancel(string $returnId, Context $context): void
    {
        $this->cancelCalls[] = $returnId;
    }

    public function returnOnCreatedAsDone(string $returnId, Context $context): void
    {
        $this->returnOnCreatedAsDoneCalls[] = $returnId;
    }
}
