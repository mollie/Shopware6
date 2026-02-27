<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Exception;

final class SubscriptionDisabledException extends SubscriptionException
{
    public function __construct(string $salesChannelId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Subscriptions are disabled for  Sales Channel %s',$salesChannelId);
        parent::__construct($message, $code, $previous);
    }
}
