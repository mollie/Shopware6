<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Exception;

final class SubscriptionNotFoundException extends SubscriptionException
{
    public function __construct(string $subscriptionId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Subscription with id %s was not found',$subscriptionId);
        parent::__construct($message, $code, $previous);
    }
}
