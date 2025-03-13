<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Service\PluginSettingsServiceInterface;

class FakePluginSettings implements PluginSettingsServiceInterface
{
    /**
     * @var string
     */
    private $fakeEnvDomain;

    public function __construct(string $fakeEnvDomain)
    {
        $this->fakeEnvDomain = $fakeEnvDomain;
    }

    public function getEnvMollieShopDomain(): string
    {
        return $this->fakeEnvDomain;
    }

    public function getEnvMollieDevMode(): bool
    {
        return false;
    }
}
