<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility;

class VersionCompare
{
    /**
     * @var string
     */
    private $swVersion;

    public function __construct(string $swVersion)
    {
        $this->swVersion = str_replace(['-RC1', '-RC2', '-RC3', '-RC4'], '', $swVersion);
    }

    public function gte(string $versionB): bool
    {
        return version_compare($this->swVersion, $versionB, '>=');
    }

    public function gt(string $versionB): bool
    {
        return version_compare($this->swVersion, $versionB, '>');
    }

    public function lt(string $version): bool
    {
        return version_compare($this->swVersion, $version, '<');
    }
}
