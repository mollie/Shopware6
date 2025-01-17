<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderFailed;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;

class OrderFailedEvent651 extends OrderFailedEvent implements ScalarValuesAware
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
