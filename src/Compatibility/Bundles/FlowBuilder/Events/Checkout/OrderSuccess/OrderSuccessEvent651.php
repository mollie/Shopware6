<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderSuccess;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;

class OrderSuccessEvent651 extends OrderSuccessEvent implements ScalarValuesAware
{
    /**
     * @return array<mixed>
     */
    public function getValues(): array
    {
        return [
            'order' => $this->order,
            'customer' => $this->customer,
        ];
    }
}
