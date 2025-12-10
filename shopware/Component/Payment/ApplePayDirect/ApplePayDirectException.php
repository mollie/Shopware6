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
}
