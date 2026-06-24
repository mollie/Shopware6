<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Exception;

final class SubscriptionWithoutOrderException extends SubscriptionException
{
    public function __construct(string $subscriptionId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Subscription %s was found but it was loaded without order',$subscriptionId);
        parent::__construct($message, $code, $previous);
    }
}
