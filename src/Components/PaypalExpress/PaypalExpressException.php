<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class PaypalExpressException extends HttpException
{
    public const PAYMENT_METHOD_DISABLED = 'PAYMENT_METHOD_DISABLED';
    public const EMPTY_CART = 'EMPTY_CART';

    public const MISSING_SESSION_ID = 'MISSING_SESSION_ID';

    public const MISSING_CART_SESSION_ID = 'MISSING_CART_SESSION_ID';
    public const MISSING_SHIPPING_ADDRESS = 'MISSING_SHIPPING_ADDRESS';
    public const MISSING_BILLING_ADDRESS = 'MISSING_BILLING_ADDRESS';

    public const BILLING_ADDRESS_PARSING_ERROR = 'BILLING_ADDRESS_PARSING_ERROR';
    public const SHIPPING_ADDRESS_PARSING_ERROR = 'SHIPPING_ADDRESS_PARSING_ERROR';

    public static function paymentNotEnabled(string $salesChannelId): PaypalExpressException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_METHOD_DISABLED,
            'Paypal Express is not enabled for SalesChannelId: {{salesChannelId}}',
            [
                'salesChannelId' => $salesChannelId,
            ]
        );
    }

    public static function cartIsEmpty(): PaypalExpressException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::EMPTY_CART,
            'Current Cart is empty'
        );
    }

    public static function missingSessionId(): PaypalExpressException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_SESSION_ID,
            'Session ID is missing, please check error logs'
        );
    }

    public static function cartSessionIdIsEmpty(): PaypalExpressException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_CART_SESSION_ID,
            'Session ID is missing in cart extensions'
        );
    }

    public static function shippingAddressMissing(): PaypalExpressException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_SHIPPING_ADDRESS,
            'Shipping Address is missing'
        );
    }

    public static function billingAddressMissing(): PaypalExpressException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_BILLING_ADDRESS,
            'Billing Address is missing'
        );
    }

    public static function billingAddressError(string $message, \stdClass $billingAddress): PaypalExpressException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BILLING_ADDRESS_PARSING_ERROR,
            'Failed to parse billing address with following error {{error}}',
            [
                'error' => $message,
                'billingAddress' => (array) $billingAddress,
            ]
        );
    }

    public static function shippingAddressError(string $message, \stdClass $shippingAddress): PaypalExpressException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SHIPPING_ADDRESS_PARSING_ERROR,
            'Failed to parse shipping address with following error {{error}}',
            [
                'error' => $message,
                'shippingAddress' => (array) $shippingAddress,
            ]
        );
    }
}
