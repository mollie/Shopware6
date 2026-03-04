<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat;

final class Storage
{
    private static array $data = [];

    /**
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        self::$data[$key] = $value;
    }

    /**
     * @param mixed $default
     *
     * @return null|mixed
     */
    public static function get(string $key,$default = null)
    {
        return self::$data[$key] ?? $default;
    }

    public static function clear(): void
    {
        self::$data = [];
    }
}
