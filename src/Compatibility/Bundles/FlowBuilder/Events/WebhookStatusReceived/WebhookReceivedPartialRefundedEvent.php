<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived;

use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;

class WebhookReceivedPartialRefundedEvent extends AbstractWebhookReceivedEvent
{
    public function getMollieStatus(): string
    {
        return MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED;
    }
}
