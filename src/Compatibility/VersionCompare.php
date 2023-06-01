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

        # no words...make it work :D
        $this->swVersion = str_replace('-RC2', '', $this->swVersion);
        $this->swVersion = str_replace('-RC1', '', $this->swVersion);
        $this->swVersion = str_replace('-RC3', '', $this->swVersion);
        $this->swVersion = str_replace('-RC4', '', $this->swVersion);
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
     * @param string $versionB
     * @return bool
     */
    public function gt(string $versionB): bool
    {
        return version_compare($this->swVersion, $versionB, '>');
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
