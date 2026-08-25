<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

final class FakePluginIdProvider extends PluginIdProvider
{
    public function __construct(private readonly string $pluginId = 'plugin-1')
    {
    }

    public function getPluginIdByBaseClass(string $pluginBaseClassName, Context $context): string
    {
        return $this->pluginId;
    }
}
