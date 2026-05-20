<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Symfony\Component\HttpFoundation\Response;

final class ChangeStateException extends SubscriptionException
{
    public const NOT_AUTHENTICATED = 'NOT_AUTHENTICATED';
    public const SUBSCRIPTION_NOT_OWNED = 'SUBSCRIPTION_NOT_OWNED';

    public static function notAuthenticated(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::NOT_AUTHENTICATED,
            'No customer is signed in'
        );
    }

    public static function notOwner(string $subscriptionId): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::SUBSCRIPTION_NOT_OWNED,
            'Subscription {{subscriptionId}} does not belong to the current customer',
            [
                'subscriptionId' => $subscriptionId,
            ]
        );
    }
}
