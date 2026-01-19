<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class RenewException extends HttpException
{
    public const SUBSCRIPTION_NOT_FOUND = 'SUBSCRIPTION_NOT_FOUND';
    public const INVALID_PAYMENT_ID = 'INVALID_PAYMENT_ID';
    public const SUBSCRIPTION_WITHOUT_ORDER = 'SUBSCRIPTION_WITHOUT_ORDER';
    public const SUBSCRIPTIONS_DISABLED = 'SUBSCRIPTIONS_DISABLED';
    public const SUBSCRIPTION_WITHOUT_ADDRESS = 'SUBSCRIPTION_WITHOUT_ADDRESS';
    public const ORDER_WITHOUT_DELIVERIES = 'ORDER_WITHOUT_DELIVERIES';
    public const SUBSCRIPTION_WITHOUT_CUSTOMER = 'SUBSCRIPTION_WITHOUT_CUSTOMER';

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

    public static function subscriptionWithoutOrder(string $subscriptionId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::SUBSCRIPTION_WITHOUT_ORDER,
            'Subscription was found but it was loaded without order: {{subscriptionId}}',[
                'subscriptionId' => $subscriptionId,
            ]
        );
    }

    public static function subscriptionsDisabled(string $subscriptionId, string $salesChannelId): self
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

    public static function invalidPaymentId(string $subscriptionId, string $molliePaymentId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::INVALID_PAYMENT_ID,
            'Failed to renew {{subscriptionId}}, payment id {{paymentId}} does not belong to this subscription',[
                'subscriptionId' => $subscriptionId,
                'paymentId' => $molliePaymentId,
            ]
        );
    }

    public static function orderWithoutTransaction(string $subscriptionId,string $orderNumber): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::INVALID_PAYMENT_ID,
            'Failed to renew {{subscriptionId}}, new created order {{orderNumber}} is without transactions',[
                'subscriptionId' => $subscriptionId,
                'orderNumber' => $orderNumber,
            ]
        );
    }

    public static function subscriptionWithoutAddress(string $subscriptionId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::SUBSCRIPTION_WITHOUT_ADDRESS,
            'Failed to renew {{subscriptionId}}, subscription was loaded without address',[
                'subscriptionId' => $subscriptionId,
            ]
        );
    }

    public static function orderWithoutDeliveries(string $subscriptionId, string $orderNumber): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::ORDER_WITHOUT_DELIVERIES,
            'Failed to renew {{subscriptionId}}, new created order {{orderNumber}} is without deliveries',[
                'subscriptionId' => $subscriptionId,
                'orderNumber' => $orderNumber,
            ]
        );
    }

    public static function subscriptionWithoutCustomer(string $subscriptionId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::SUBSCRIPTION_WITHOUT_CUSTOMER,
            'Failed to renew {{subscriptionId}}, subscription order has no customer ',[
                'subscriptionId' => $subscriptionId,
            ]
        );
    }
}
