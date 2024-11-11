<?php declare(strict_types=1);

namespace Kiener\MolliePayments;

use Exception;
use Kiener\MolliePayments\Compatibility\DependencyLoader;
use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Components\Installer\PluginInstaller;
use Kiener\MolliePayments\Repository\CustomFieldSet\CustomFieldSetRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MolliePayments extends Plugin
{
    const PLUGIN_VERSION = '4.12.0';


    /**
     * @param ContainerBuilder $container
     * @throws Exception
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->container = $container;
        $shopwareVersion = $this->container->getParameter('kernel.shopware_version');
        if (!is_string($shopwareVersion)) {
            $shopwareVersion= Kernel::SHOPWARE_FALLBACK_VERSION;
        }
        # load the dependencies that are compatible
        # with our current shopware version
        $loader = new DependencyLoader($this->container, new VersionCompare($shopwareVersion));
        $loader->loadServices();
        $loader->prepareStorefrontBuild();
    }


    /**
     * @param InstallContext $context
     * @return void
     */
    public function install(InstallContext $context): void
    {
        parent::install($context);
        if ($this->container === null) {
            throw new Exception('Container is not initialized');
        }
        # that's the only part we use the Shopware repository directly,
        # and not our custom one, because our repositories are not yet registered in this function
        /** @var EntityRepository $shopwareRepoCustomFields */
        $shopwareRepoCustomFields = $this->container->get('custom_field_set.repository');

        if ($shopwareRepoCustomFields !== null) {
            $mollieRepoCustomFields = new CustomFieldSetRepository($shopwareRepoCustomFields);
        }

        $this->runDbMigrations($context->getMigrationCollection());
    }

    /**
     * @param UpdateContext $context
     * @throws \Doctrine\DBAL\Exception
     * @return void
     */
    public function update(UpdateContext $context): void
    {
        parent::update($context);

        if ($context->getPlugin()->isActive() === true) {
            # only prepare our whole plugin
            # if it is indeed active at the moment.
            # otherwise service would not be found of course
            $this->preparePlugin($context->getContext());

            $this->runDbMigrations($context->getMigrationCollection());
        }
    }
    /**
     * @param ActivateContext $context
     * @throws \Doctrine\DBAL\Exception
     * @return void
     */
    public function activate(ActivateContext $context): void
    {
        parent::activate($context);

        $this->preparePlugin($context->getContext());

        $this->runDbMigrations($context->getMigrationCollection());
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->container === null) {
            return;
        }
        /** @var Container $container */
        $container = $this->container;
        
        $shopwareVersion = $container->getParameter('kernel.shopware_version');
        if (!is_string($shopwareVersion)) {
            $shopwareVersion = Kernel::SHOPWARE_FALLBACK_VERSION;
        }
        # load the dependencies that are compatible
        # with our current shopware version

        $loader = new DependencyLoader($container, new VersionCompare($shopwareVersion));
        $loader->registerDependencies();
    }


    /**
     * @param Context $context
     * @throws \Doctrine\DBAL\Exception
     */
    private function preparePlugin(Context $context): void
    {
        if ($this->container === null) {
            throw new Exception('Container is not initialized');
        }
        /** @var PluginInstaller $pluginInstaller */
        $pluginInstaller = $this->container->get(PluginInstaller::class);

        $pluginInstaller->install($context);
    }

    /**
     * @param MigrationCollection $migrationCollection
     * @return void
     */
    private function runDbMigrations(MigrationCollection $migrationCollection): void
    {
        $migrationCollection->migrateInPlace();
    }
}
