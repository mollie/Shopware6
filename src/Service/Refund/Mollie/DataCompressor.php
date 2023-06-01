<?php

namespace Kiener\MolliePayments\Service\Refund\Mollie;

class DataCompressor
{
    /**
     * this will be the string length where
     * compression will be active. all strings
     * with a length >= will be compressed
     */
    private const LENGTH_THRESHOLD = 32;

    /**
     * this is the length of the part in the
     * beginning and end of the string to be compressed.
     * this value * 2 is the final length
     */
    private const LENGTH_PARTS = 4;


    /**
     * @param string $value
     * @return string
     */
    public function compress(string $value): string
    {
        if (strlen($value) < self::LENGTH_THRESHOLD) {
            return $value;
        }

        $firstPart = substr($value, 0, self::LENGTH_PARTS);
        $lastPart = substr($value, -self::LENGTH_PARTS);

        return $firstPart . $lastPart;
    }
}
