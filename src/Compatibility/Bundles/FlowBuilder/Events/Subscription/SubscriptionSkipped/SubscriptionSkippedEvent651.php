<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionSkipped;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;

class SubscriptionSkippedEvent651 extends SubscriptionSkippedEvent implements ScalarValuesAware
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
