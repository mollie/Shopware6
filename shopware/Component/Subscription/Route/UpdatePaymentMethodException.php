<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Symfony\Component\HttpFoundation\Response;

final class UpdatePaymentMethodException extends SubscriptionException
{
    public const SUBSCRIPTIONS_DISABLED = 'SUBSCRIPTIONS_DISABLED';
    public const SUBSCRIPTION_NOT_OWNED = 'SUBSCRIPTION_NOT_OWNED';
    public const NOT_AUTHENTICATED = 'NOT_AUTHENTICATED';
    public const PAYMENT_UPDATE_NOT_ALLOWED = 'PAYMENT_UPDATE_NOT_ALLOWED';
    public const SUBSCRIPTION_NOT_ACTIVE = 'SUBSCRIPTION_NOT_ACTIVE';

    public static function subscriptionsDisabled(string $subscriptionId, string $salesChannelId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::SUBSCRIPTIONS_DISABLED,
            'Cannot update payment method for {{subscriptionId}}, subscriptions are disabled for sales channel {{salesChannelId}}',
            [
                'subscriptionId' => $subscriptionId,
                'salesChannelId' => $salesChannelId,
            ]
        );
    }

    public static function paymentUpdateNotAllowed(string $subscriptionId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::PAYMENT_UPDATE_NOT_ALLOWED,
            'Updating the payment method of subscription {{subscriptionId}} is not allowed in its current status',
            [
                'subscriptionId' => $subscriptionId,
            ]
        );
    }

    public static function subscriptionNotActive(string $subscriptionId, string $status): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::SUBSCRIPTION_NOT_ACTIVE,
            'Subscription {{subscriptionId}} is not active and cannot be edited (status: {{status}})',
            [
                'subscriptionId' => $subscriptionId,
                'status' => $status,
            ]
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

    public static function notAuthenticated(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::NOT_AUTHENTICATED,
            'No customer is signed in'
        );
    }
}
