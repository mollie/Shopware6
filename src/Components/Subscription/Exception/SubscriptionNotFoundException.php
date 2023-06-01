<?php

namespace Kiener\MolliePayments\Components\Subscription\Exception;

class SubscriptionNotFoundException extends \Exception
{
    /**
     * @param string $subscriptionId
     * @param null|\Throwable $previous
     */
    public function __construct(string $subscriptionId, ?\Throwable $previous = null)
    {
        $message = 'Subscription ' . $subscriptionId . ' not found in Shopware';

        parent::__construct($message, 0, $previous);
    }
}
