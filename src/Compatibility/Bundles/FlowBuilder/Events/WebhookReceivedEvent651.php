<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;

class WebhookReceivedEvent651 extends WebhookReceivedEvent implements ScalarValuesAware
{
    /**
     * @return array<mixed>
     */
    public function getValues(): array
    {
        return [
            'order' => $this->order,
            'mollieStatus' => $this->status,
        ];
    }
}
