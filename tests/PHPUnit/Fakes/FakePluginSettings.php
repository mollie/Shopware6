<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Service\PluginSettingsServiceInterface;

class FakePluginSettings implements PluginSettingsServiceInterface
{

    /**
     * @var string
     */
    private $fakeEnvDomain;


    /**
     * @param string $fakeEnvDomain
     */
    public function __construct(string $fakeEnvDomain)
    {
        $this->fakeEnvDomain = $fakeEnvDomain;
    }


    /**
     * @return string
     */
    public function getEnvMollieShopDomain(): string
    {
        return $this->fakeEnvDomain;
    }

    /**
     * @return bool
     */
    public function getEnvMollieDevMode(): bool
    {
        return false;
    }

}
