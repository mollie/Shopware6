<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action\Exception;

final class SubscriptionActiveException extends \Exception
{
    public function __construct(string $subscriptionId)
    {
        $message = sprintf('Subscription with id %s is already active.', $subscriptionId);

        parent::__construct($message);
    }
}
