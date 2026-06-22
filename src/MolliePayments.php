<?php
declare(strict_types=1);

namespace Kiener\MolliePayments;

use Mollie\Shopware\Component\Installer\PluginInstaller;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class MolliePayments extends Plugin
{
    public const PLUGIN_VERSION = '5.0.0-beta4';

    public function install(InstallContext $context): void
    {
        parent::install($context);
        if ($this->container === null) {
            throw new \Exception('Container is not initialized');
        }

        $this->runDbMigrations($context->getMigrationCollection());
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(UpdateContext $context): void
    {
        parent::update($context);

        if ($context->getPlugin()->isActive() === true) {
            $this->runDbMigrations($context->getMigrationCollection());
            // only prepare our whole plugin
            // if it is indeed active at the moment.
            // otherwise service would not be found of course
            $this->preparePlugin($context->getContext());
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function activate(ActivateContext $context): void
    {
        parent::activate($context);

        $this->runDbMigrations($context->getMigrationCollection());

        $this->preparePlugin($context->getContext());
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function preparePlugin(Context $context): void
    {
        if ($this->container === null) {
            throw new \Exception('Container is not initialized');
        }
        /** @var PluginInstaller $pluginInstaller */
        $pluginInstaller = $this->container->get(PluginInstaller::class);

        $pluginInstaller->install($context);
    }

    private function runDbMigrations(MigrationCollection $migrationCollection): void
    {
        $migrationCollection->migrateInPlace();
    }
}
