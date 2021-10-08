<?php

namespace Kiener\MolliePayments\Compatibility;


class VersionCompare
{

    /**
     * @param string $versionA
     * @param string $versionB
     * @return bool
     */
    public static function gte(string $versionA, string $versionB): bool
    {
        return version_compare($versionA, $versionB, '>=');
    }

}
