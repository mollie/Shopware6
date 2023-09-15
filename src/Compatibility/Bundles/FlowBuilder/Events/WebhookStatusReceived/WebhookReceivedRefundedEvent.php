<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived;

use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;

class WebhookReceivedRefundedEvent extends AbstractWebhookReceivedEvent
{
    /**
     * @return string
     */
    public function getMollieStatus(): string
    {
        return MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED;
    }
}
