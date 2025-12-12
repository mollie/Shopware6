<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class ApplePayDirectException extends HttpException
{
    public const INVALID_SHIPPING_COUNTRY = 'INVALID_SHIPPING_COUNTRY';
    public const INVALID_VALIDATION_URL = 'INVALID_VALIDATION_URL';
    public const CREATE_SESSION_FAILED = 'CREATE_SESSION_FAILED';
    public const MISSING_SHIPPING_METHOD = 'MISSING_SHIPPING_METHOD';
    public const PAYMENT_TOKEN_NOT_FOUND = 'PAYMENT_TOKEN_NOT_FOUND';
    public const PAYMENT_DISABLED = 'PAYMENT_DISABLED';
    public const CUSTOMER_ACTION_FAILED = 'CUSTOMER_ACTION_FAILED';
    public const CART_ACTION_FAILED = 'CART_ACTION_FAILED';
    public const ORDER_ACTION_FAILED = 'ORDER_ACTION_FAILED';
    public const PAYMENT_FAILED = 'PAYMENT_FAILED';

    public static function invalidCountryCode(string $countryCode): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SHIPPING_COUNTRY,
            'Invalid country code {{countryCode}} for shipping',
            [
                'countryCode' => $countryCode,
            ]
        );
    }

    public static function validationUrlNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_VALIDATION_URL,
            'Please provide a validation url'
        );
    }

    public static function sessionRequestFailed(\Throwable $exception): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CREATE_SESSION_FAILED,
            'Failed to request apple pay direct session',
            [],
            $exception
        );
    }

    public static function missingShippingMethodIdentifier(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_SHIPPING_METHOD,
            'Missing shipping method identifier',
        );
    }

    public static function paymentTokenNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_TOKEN_NOT_FOUND,
            'Payment token not found',
        );
    }

    public static function paymentDisabled(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_DISABLED,
            'Applepay Direct payment is disabled',
        );
    }

    public static function customerActionFailed(\Throwable $exception): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_ACTION_FAILED,
            'Failed to login by email or create a customer guest account',
            [],
            $exception
        );
    }

    public static function loadCartFailed(\Throwable $exception): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_ACTION_FAILED,
            'Failed to load apple pay cart',
            [],
            $exception
        );
    }

    public static function createOrderFailed(\Throwable $exception): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ORDER_ACTION_FAILED,
            'Failed to create an order from cart',
            [],
            $exception
        );
    }

    public static function paymentFailed(\Throwable $exception,string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_FAILED,
            'Apple pay payment failed',
            [
                'orderId' => $orderId,
            ],
            $exception
        );
    }
}
