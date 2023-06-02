<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStarted;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;

class RefundStartedEvent651 extends RefundStartedEvent implements ScalarValuesAware
{
    /**
     * @return array<mixed>
     */
    public function getValues(): array
    {
        return [
            'order' => $this->order,
        ];
    }
}
