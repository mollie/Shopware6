<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class UpdatePaymentMethodActionException extends HttpException
{
    public const MISSING_TMP_TRANSACTION = 'MISSING_TMP_TRANSACTION';
    public const PAYMENT_NOT_APPROVED = 'PAYMENT_NOT_APPROVED';
    public const PAYMENT_WITHOUT_MANDATE = 'PAYMENT_WITHOUT_MANDATE';

    public static function missingTmpTransaction(string $subscriptionId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::MISSING_TMP_TRANSACTION,
            'No temporary transaction is registered for subscription {{subscriptionId}}',
            [
                'subscriptionId' => $subscriptionId,
            ]
        );
    }

    public static function paymentNotApproved(string $subscriptionId, string $paymentId, string $status): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::PAYMENT_NOT_APPROVED,
            'Payment {{paymentId}} for subscription {{subscriptionId}} is not in an approved state (status: {{status}})',
            [
                'subscriptionId' => $subscriptionId,
                'paymentId' => $paymentId,
                'status' => $status,
            ]
        );
    }

    public static function paymentWithoutMandate(string $subscriptionId, string $paymentId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::PAYMENT_WITHOUT_MANDATE,
            'Payment {{paymentId}} for subscription {{subscriptionId}} does not contain a mandate',
            [
                'subscriptionId' => $subscriptionId,
                'paymentId' => $paymentId,
            ]
        );
    }
}
