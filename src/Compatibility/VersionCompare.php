<?php

namespace Kiener\MolliePayments\Compatibility;


class VersionCompare
{

    /**
     * @var string
     */
    private $swVersion;


    /**
     * @param string $swVersion
     */
    public function __construct(string $swVersion)
    {
        $this->swVersion = $swVersion;
    }


    /**
     * @param string $versionB
     * @return bool
     */
    public function gte(string $versionB): bool
    {
        return version_compare($this->swVersion, $versionB, '>=');
    }

    /**
     * @param string $version
     * @return bool
     */
    public function lt(string $version): bool
    {
        return version_compare($this->swVersion, $version, '<');
    }

}
