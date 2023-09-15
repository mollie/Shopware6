<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionCancelled;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;

class SubscriptionCancelledEvent651 extends SubscriptionCancelledEvent implements ScalarValuesAware
{
    /**
     * @return array<mixed>
     */
    public function getValues(): array
    {
        return [
            'customer' => $this->customer,
            'subscription' => $this->subscription,
        ];
    }
}
