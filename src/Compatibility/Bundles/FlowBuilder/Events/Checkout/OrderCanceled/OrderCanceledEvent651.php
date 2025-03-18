<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderCanceled;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;

class OrderCanceledEvent651 extends OrderCanceledEvent implements ScalarValuesAware
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
