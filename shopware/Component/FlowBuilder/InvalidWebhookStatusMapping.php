<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder;

final class InvalidWebhookStatusMapping extends \Exception
{
    public function __construct(string $status)
    {
        $message = 'Failed to find event for payment status: ' . $status;
        parent::__construct($message);
    }
}
