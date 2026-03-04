<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Exception;

final class SubscriptionWithoutAddressException extends SubscriptionException
{
    public function __construct(string $subscriptionId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Subscription %s does not have an address in Shopware',$subscriptionId);
        parent::__construct($message, $code, $previous);
    }
}
