<?php
declare(strict_types=1);

namespace Kiener\MolliePayments;

use Mollie\Shopware\Component\Installer\MollieDataRemover;
use Mollie\Shopware\Component\Installer\PluginInstaller;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class MolliePayments extends Plugin
{
    public const PLUGIN_VERSION = '5.3.0';

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

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        // the merchant chose to keep the data - never touch anything
        if ($context->keepUserData()) {
            return;
        }

        // The data remover and its tagged steps are only available in the container when the
        // plugin's services are loaded (i.e. it is active). If it is uninstalled while inactive
        // there is nothing wired up to remove, so we skip safely.
        if ($this->container === null || $this->container->has(MollieDataRemover::class) === false) {
            return;
        }

        /** @var MollieDataRemover $dataRemover */
        $dataRemover = $this->container->get(MollieDataRemover::class);
        $dataRemover->removeAllData($context->getContext());
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
