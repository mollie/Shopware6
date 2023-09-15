<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionReminded;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;

class SubscriptionRemindedEvent651 extends SubscriptionRemindedEvent implements ScalarValuesAware
{
    /**
     * @return array<mixed>
     */
    public function getValues(): array
    {
        return [
            'customer' => $this->customer,
            'subscription' => $this->subscription,
            'salesChannel' => $this->salesChannel,
        ];
    }
}
