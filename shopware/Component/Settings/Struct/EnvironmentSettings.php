<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

final class EnvironmentSettings
{
    public function __construct(private bool $devMode, private bool $cypressMode)
    {
    }

    public function isDevMode(): bool
    {
        return $this->devMode;
    }

    public function isCypressMode(): bool
    {
        return $this->cypressMode;
    }
}
