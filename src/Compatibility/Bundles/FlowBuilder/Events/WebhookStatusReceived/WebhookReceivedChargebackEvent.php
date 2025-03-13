<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived;

use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;

class WebhookReceivedChargebackEvent extends AbstractWebhookReceivedEvent
{
    public function getMollieStatus(): string
    {
        return MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK;
    }
}
