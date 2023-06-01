<?php

namespace Kiener\MolliePayments\Compatibility;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class DependencyLoader
{
    /**
     * @var Container
     */
    private $container;


    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @throws \Exception
     */
    public function loadServices(): void
    {
        /** @var string $version */
        $version = $this->container->getParameter('kernel.shopware_version');

        $versionCompare = new VersionCompare($version);


        /** @var ContainerBuilder $containerBuilder */
        $containerBuilder = $this->container;

        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../Resources/config'));

        # load all our base services that
        # we need all the time
        $loader->load('services.xml');

        # now load our base compatibility services
        # that already wrap our functions for different shopware versions
        $loader->load('compatibility/base.xml');


        # load Flow Builder
        $loader->load('compatibility/flowbuilder/all_versions.xml');

        if ($versionCompare->gte('6.4.6.0')) {
            $loader->load('compatibility/flowbuilder/6.4.6.0.xml');
        }

        # load other data
        if ($versionCompare->gte('6.4')) {
            $loader->load('compatibility/services_6.4.xml');
        } elseif ($versionCompare->gte('6.3.5.0')) {
            $loader->load('compatibility/services_6.3.5.0.xml');
        }

        if ($versionCompare->gte('6.5')) {
            $loader->load('compatibility/snippets_6.5.xml');
        } else {
            $loader->load('compatibility/snippets.xml');
        }

        if ($versionCompare->gte('6.5')) {
            $loader->load('compatibility/controller_6.5.xml');
        } else {
            $loader->load('compatibility/controller.xml');
        }


        $composerDevReqsInstalled = file_exists(__DIR__ . '/../../vendor/bin/phpunit');

        if ($composerDevReqsInstalled) {
            $dirFixtures = __DIR__ . '/../../tests/Fixtures';

            if (is_dir($dirFixtures)) {
                # we need to tell Shopware to load our custom fixtures
                # from our TEST autoload-dev area....
                $classLoader = new ClassLoader();
                $classLoader->addPsr4("MolliePayments\\Fixtures\\", $dirFixtures, true);
                $classLoader->register();

                $loader->load('services/fixtures/fixtures.xml');
            }
        }
    }

    /**
     * @return void
     */
    public function prepareStorefrontBuild(): void
    {
        /** @var string $version */
        $version = $this->container->getParameter('kernel.shopware_version');

        $versionCompare = new VersionCompare($version);

        $pluginRoot = __DIR__ . '/../..';

        $distFileFolder = $pluginRoot . '/src/Resources/app/storefront/dist/storefront/js';

        if (!file_exists($distFileFolder)) {
            mkdir($distFileFolder, 0777, true);
        }

        if ($versionCompare->gte('6.5')) {
            $file = $pluginRoot . '/src/Resources/app/storefront/dist/mollie-payments-65.js';
            $target = $distFileFolder . '/mollie-payments.js';
        } else {
            $file = $pluginRoot . '/src/Resources/app/storefront/dist/mollie-payments-64.js';
            $target = $distFileFolder . '/mollie-payments.js';
        }

        if (file_exists($file) && !file_exists($target)) {
            # while we use our current webpack approach
            # we must not use this.
            # also it's not perfectly working somehow
            # copy($file, $target);
        }
    }

    /**
     * @param string $pluginPath
     * @return string
     */
    public function getRoutesPath(string $pluginPath): string
    {
        /** @var string $version */
        $version = $this->container->getParameter('kernel.shopware_version');

        $versionCompare = new VersionCompare($version);

        if ($versionCompare->gte('6.5')) {
            return $pluginPath . '/Resources/config/compatibility/routes/sw65';
        }

        return $pluginPath . '/Resources/config/compatibility/routes/sw6';
    }
}
