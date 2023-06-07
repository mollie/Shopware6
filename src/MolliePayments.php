<?php declare(strict_types=1);

namespace Kiener\MolliePayments;

use Exception;
use Kiener\MolliePayments\Compatibility\DependencyLoader;
use Kiener\MolliePayments\Components\Installer\PluginInstaller;
use Kiener\MolliePayments\Repository\CustomFieldSet\CustomFieldSetRepository;
use Kiener\MolliePayments\Service\CustomFieldService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class MolliePayments extends Plugin
{
    const PLUGIN_VERSION = '4.0.0';


    /**
     * @param ContainerBuilder $container
     * @throws Exception
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->container = $container;

        # load the dependencies that are compatible
        # with our current shopware version
        $loader = new DependencyLoader($this->container);
        $loader->loadServices();
        $loader->prepareStorefrontBuild();
    }


    /**
     * @return void
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * @param RoutingConfigurator $routes
     * @param string $environment
     * @return void
     */
    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        if (!$this->isActive()) {
            return;
        }

        /** @var Container $container */
        $container = $this->container;

        $loader = new DependencyLoader($container);

        $routeDir = $loader->getRoutesPath($this->getPath());

        $fileSystem = new Filesystem();

        if ($fileSystem->exists($routeDir)) {
            $routes->import($routeDir . '/{routes}/*' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($routeDir . '/{routes}/' . $environment . '/**/*' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($routeDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($routeDir . '/{routes}_' . $environment . Kernel::CONFIG_EXTS, 'glob');
        }
    }

    /**
     * @param InstallContext $context
     * @return void
     */
    public function install(InstallContext $context): void
    {
        parent::install($context);

        # that's the only part we use the Shopware repository directly,
        # and not our custom one, because our repositories are not yet registered in this function
        /** @var EntityRepository $shopwareRepoCustomFields */
        $shopwareRepoCustomFields = $this->container->get('custom_field_set.repository');
        $mollieRepoCustomFields = new CustomFieldSetRepository($shopwareRepoCustomFields);

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
     * @param InstallContext $context
     * @return void
     */
    public function postInstall(InstallContext $context): void
    {
        parent::postInstall($context);
    }

    /**
     * @param UninstallContext $context
     * @return void
     */
    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);
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

    /**
     * @param DeactivateContext $context
     * @return void
     */
    public function deactivate(DeactivateContext $context): void
    {
        parent::deactivate($context);
    }

    /**
     * @param Context $context
     * @throws \Doctrine\DBAL\Exception
     */
    private function preparePlugin(Context $context): void
    {
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
