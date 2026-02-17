<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action\Exception;

final class SubscriptionNotActiveException extends \Exception
{
    public function __construct(string $subscriptionId)
    {
        $message = sprintf('Subscription with id %s is not active.', $subscriptionId);

        parent::__construct($message);
    }
}
