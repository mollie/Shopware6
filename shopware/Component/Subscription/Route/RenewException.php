<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class RenewException  extends HttpException
{
    const SUBSCRIPTION_NOT_FOUND = 'SUBSCRIPTION_NOT_FOUND';
    const INVALID_PAYMENT_ID = 'INVALID_PAYMENT_ID';
    const SUBSCRIPTION_WITHOUT_ORDER = 'SUBSCRIPTION_WITHOUT_ORDER';
    const SUBSCRIPTIONS_DISABLED = 'SUBSCRIPTIONS_DISABLED';

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

    public static function subscriptionWithoutOrder(string $subscriptionId):self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::SUBSCRIPTION_WITHOUT_ORDER,
            'Subscription was found but it was loaded without order: {{subscriptionId}}',[
                'subscriptionId' => $subscriptionId,
            ]
        );
    }

    public static function subscriptionsDisabled(string $subscriptionId, string $salesChannelId):self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::SUBSCRIPTIONS_DISABLED,
            'Failed to renew {{subscriptionId}}, subscriptions are disabled for the sales channel {{salesChannelId}}',[
                'subscriptionId' => $subscriptionId,
                'salesChannelId' => $salesChannelId,
            ]
        );
    }

    public static function invalidPaymentId(string $subscriptionId, string $molliePaymentId):self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::INVALID_PAYMENT_ID,
            'Failed to renew {{subscriptionId}}, payment id {{salesChannelId}} does not belong to this subscription',[
                'subscriptionId' => $subscriptionId,
                'salesChannelId' => $molliePaymentId,
            ]
        );
    }
}