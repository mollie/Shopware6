<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionEnded;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;

class SubscriptionEndedEvent651 extends SubscriptionEndedEvent implements ScalarValuesAware
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
