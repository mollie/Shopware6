<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Exception;

class SubscriptionSkippedException extends \Exception
{
    public function __construct(string $subscriptionId, string $transactionId, ?\Throwable $previous = null)
    {
        $message = 'Skip renewal of subscription ' . $subscriptionId . '. Incoming transaction ' . $transactionId . ' of Mollie is not successfully paid';

        parent::__construct($message, 0, $previous);
    }
}
