<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Voucher;

class VoucherType
{
    public const TYPE_NOTSET = '';
    public const TYPE_NONE = '0';
    public const TYPE_ECO = '1';
    public const TYPE_MEAL = '2';
    public const TYPE_GIFT = '3';


    /**
     * @param string $type
     * @return bool
     */
    public static function isVoucherProduct(string $type): bool
    {
        if ($type === self::TYPE_ECO) {
            return true;
        }

        if ($type === self::TYPE_MEAL) {
            return true;
        }

        if ($type === self::TYPE_GIFT) {
            return true;
        }

        return false;
    }
}
