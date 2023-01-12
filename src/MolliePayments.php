<?php declare(strict_types=1);

namespace Kiener\MolliePayments;

use Exception;
use Kiener\MolliePayments\Compatibility\DependencyLoader;
use Kiener\MolliePayments\Components\Installer\PluginInstaller;
use Kiener\MolliePayments\Service\CustomFieldService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MolliePayments extends Plugin
{
    const PLUGIN_VERSION = '3.4.0';


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
        $loader = new DependencyLoader($container);
        $loader->loadServices();
    }


    /**
     * @return void
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * @param InstallContext $context
     * @return void
     */
    public function install(InstallContext $context): void
    {
        parent::install($context);

        /** @var EntityRepositoryInterface $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field_set.repository');

        // Add custom fields
        $customFieldService = new CustomFieldService($customFieldRepository);

        $customFieldService->addCustomFields($context->getContext());

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
