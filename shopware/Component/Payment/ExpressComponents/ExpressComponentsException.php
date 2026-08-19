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
