<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class PaymentStatus extends AbstractEnum
{
    public const OPEN = 'open';
    public const PENDING = 'pending';
    public const AUTHORIZED = 'authorized';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const CANCELED = 'canceled';
    public const EXPIRED = 'expired';

    public function isFailed(): bool
    {
        $list = [
            self::CANCELED,
            self::FAILED,
            self::EXPIRED,
        ];
        $value = (string) $this;

        return in_array($value, $list, true);
    }

    public function isCancelled(): bool
    {
        return (string) $this === self::CANCELED;
    }

    public function getShopwareHandlerMethod(): string
    {
        $statusMapping = [
            self::PAID => 'paid',
            self::CANCELED => 'cancel',
            self::AUTHORIZED => 'authorize',
            self::FAILED => 'fail',
        ];
        $mollieStatus = (string) $this;

        return $statusMapping[$mollieStatus] ?? '';
    }

    protected function getPossibleValues(): array
    {
        return [
            self::OPEN,
            self::PENDING,
            self::AUTHORIZED,
            self::PAID,
            self::CANCELED,
            self::EXPIRED,
            self::FAILED,
        ];
    }
}
