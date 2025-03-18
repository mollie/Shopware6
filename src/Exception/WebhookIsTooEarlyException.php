<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class WebhookIsTooEarlyException extends ShopwareHttpException
{
    public const MOLLIE_PAYMENTS__WEBHOOK_TOO_EARLY = 'MOLLIE_PAYMENTS__WEBHOOK_TOO_EARLY';

    public function __construct(string $oderNumber, \DateTimeInterface $now, \DateTimeInterface $updatedTime)
    {
        $message = 'Webhook is too early for order: {{orderNumber}}. Request will be accepted after: {{lastUpdateTime}}. Current Time is :{{now}}';
        $parameters = [
            'orderNumber' => $oderNumber,
            'lastUpdateTime' => $updatedTime->format('Y-m-d H:i:s'),
            'now' => $now->format('Y-m-d H:i:s'),
        ];

        parent::__construct($message, $parameters);
    }

    public function getErrorCode(): string
    {
        return self::MOLLIE_PAYMENTS__WEBHOOK_TOO_EARLY;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_TOO_EARLY;
    }
}
