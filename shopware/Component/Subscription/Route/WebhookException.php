<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class WebhookException extends HttpException
{

    const SUBSCRIPTION_WITHOUT_PAYMENT_ID = 'SUBSCRIPTION_WITHOUT_PAYMENT_ID';

    public static function paymentIdNotProvided(string $subscriptionId)
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SUBSCRIPTION_WITHOUT_PAYMENT_ID,
            'Subscription webhook without mollie payment id: {{subscriptionId}}',[
                'subscriptionId' => $subscriptionId,
            ]
        );
    }
}