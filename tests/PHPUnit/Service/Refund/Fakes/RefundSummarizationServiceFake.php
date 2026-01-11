<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\Refund\Fakes;

use Kiener\MolliePayments\Service\Refund\RefundSummarizationService;

class RefundSummarizationServiceFake extends RefundSummarizationService
{
    #[\Override]
    public function getLineItemsRefundSum(array $items): float
    {
        return 1;
    }
}
