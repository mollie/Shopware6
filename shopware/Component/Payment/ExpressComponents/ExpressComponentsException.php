<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class ExpressComponentsException extends HttpException
{
    public const FEATURE_DISABLED = 'EXPRESS_COMPONENTS_DISABLED';
    public const MISSING_CART_TOKEN = 'MISSING_CART_TOKEN';
    public const MISSING_CART_SESSION_ID = 'MISSING_CART_SESSION_ID';
    public const SESSION_NOT_COMPLETED = 'SESSION_NOT_COMPLETED';
    public const MISSING_ADDRESS = 'MISSING_ADDRESS';
    public const PAYMENT_METHOD_NOT_FOUND = 'PAYMENT_METHOD_NOT_FOUND';
    public const MISSING_ORDER_SESSION_ID = 'MISSING_ORDER_SESSION_ID';
    public const ORDER_NOT_FOUND = 'ORDER_NOT_FOUND';
    public const ORDER_TRANSACTION_MISSING = 'ORDER_TRANSACTION_MISSING';

    public static function notEnabled(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FEATURE_DISABLED,
            'Express components are not enabled for SalesChannelId: {{salesChannelId}}',
            [
                'salesChannelId' => $salesChannelId,
            ]
        );
    }

    public static function cartTokenIsEmpty(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_CART_TOKEN,
            'Cart token is missing in the request'
        );
    }

    public static function orderSessionIdIsEmpty(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_ORDER_SESSION_ID,
            'Session ID is missing in the custom fields of order {{orderId}}',
            [
                'orderId' => $orderId,
            ]
        );
    }

    public static function orderTransactionMissing(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ORDER_TRANSACTION_MISSING,
            'Order {{orderId}} has no transaction the payment could be attached to',
            [
                'orderId' => $orderId,
            ]
        );
    }

    public static function orderNotFound(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ORDER_NOT_FOUND,
            'Order {{orderId}} not found',
            [
                'orderId' => $orderId,
            ]
        );
    }

    public static function sessionNotCompleted(string $sessionId, string $status): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SESSION_NOT_COMPLETED,
            'Session {{sessionId}} is not completed, status is {{status}}',
            [
                'sessionId' => $sessionId,
                'status' => $status,
            ]
        );
    }

    public static function addressMissing(string $sessionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_ADDRESS,
            'Session {{sessionId}} does not carry a billing and shipping address',
            [
                'sessionId' => $sessionId,
            ]
        );
    }

    public static function paymentMethodNotFound(string $method, string $salesChannelId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_METHOD_NOT_FOUND,
            'No payment method found for mollie method {{method}} in SalesChannelId {{salesChannelId}}',
            [
                'method' => $method,
                'salesChannelId' => $salesChannelId,
            ]
        );
    }

    public static function cartSessionIdIsEmpty(string $cartToken): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_CART_SESSION_ID,
            'Session ID is missing in the extensions of cart {{cartToken}}',
            [
                'cartToken' => $cartToken,
            ]
        );
    }
}
