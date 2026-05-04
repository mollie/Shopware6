<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Symfony\Component\HttpFoundation\Response;

final class UpdateAddressException extends SubscriptionException
{
    public const SUBSCRIPTIONS_DISABLED = 'SUBSCRIPTIONS_DISABLED';
    public const ADDRESS_EDITING_DISABLED = 'ADDRESS_EDITING_DISABLED';
    public const SUBSCRIPTION_NOT_OWNED = 'SUBSCRIPTION_NOT_OWNED';
    public const NOT_AUTHENTICATED = 'NOT_AUTHENTICATED';
    public const REQUIRED_FIELD_MISSING = 'REQUIRED_FIELD_MISSING';

    public static function subscriptionsDisabled(string $subscriptionId, string $salesChannelId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::SUBSCRIPTIONS_DISABLED,
            'Cannot update address for {{subscriptionId}}, subscriptions are disabled for sales channel {{salesChannelId}}',
            [
                'subscriptionId' => $subscriptionId,
                'salesChannelId' => $salesChannelId,
            ]
        );
    }

    public static function addressEditingDisabled(string $subscriptionId, string $salesChannelId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::ADDRESS_EDITING_DISABLED,
            'Address editing is disabled for sales channel {{salesChannelId}} (subscription {{subscriptionId}})',
            [
                'subscriptionId' => $subscriptionId,
                'salesChannelId' => $salesChannelId,
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

    public static function requiredFieldMissing(string $field): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::REQUIRED_FIELD_MISSING,
            'Required address field {{field}} is missing',
            [
                'field' => $field,
            ]
        );
    }
}
