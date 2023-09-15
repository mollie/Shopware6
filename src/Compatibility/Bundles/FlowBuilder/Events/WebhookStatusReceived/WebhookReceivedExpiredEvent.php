<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived;

use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;

class WebhookReceivedExpiredEvent extends AbstractWebhookReceivedEvent
{
    /**
     * @return string
     */
    public function getMollieStatus(): string
    {
        return MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED;
    }
}
