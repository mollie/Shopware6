<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionException extends HttpException
{
    public const SUBSCRIPTION_NOT_FOUND = 'SUBSCRIPTION_NOT_FOUND';

    public static function subscriptionNotFound(string $subscriptionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SUBSCRIPTION_NOT_FOUND,
            'Subscription not found: {{subscriptionId}}',[
                'subscriptionId' => $subscriptionId,
            ]
        );
    }
}
