<?php

namespace Kiener\MolliePayments\Compatibility;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class DependencyLoader
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var VersionCompare
     */
    private $versionCompare;


    /**
     * @param Container $container
     */
    public function __construct(ContainerInterface $container, VersionCompare $versionCompare)
    {
        $this->container = $container;
        $this->versionCompare = $versionCompare;
    }

    /**
     * @throws \Exception
     */
    public function loadServices(): void
    {


        /** @var ContainerBuilder $containerBuilder */
        $containerBuilder = $this->container;

        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../Resources/config'));


        # load Flow Builder
        $loader->load('compatibility/flowbuilder/all_versions.xml');

        if ($this->versionCompare->gte('6.4.6.0')) {
            $loader->load('compatibility/flowbuilder/6.4.6.0.xml');
        }

        if ($this->shouldLoadFixtures()) {
            $loader->load('services/fixtures/fixtures.xml');
        }
    }

    public function registerDependencies(): void
    {
        $classLoader = new ClassLoader();

        $this->registerPolyfillsAutoloader($classLoader);
        $this->registerFixturesAutoloader($classLoader);

        $classLoader->register();
    }

    private function registerPolyfillsAutoloader(ClassLoader $classLoader): void
    {
        $classLoader->addPsr4("Shopware\\Core\\", __DIR__ . '/../../polyfill/Shopware/Core', true);
    }

    private function registerFixturesAutoloader(ClassLoader $classLoader): void
    {
        if ($this->shouldLoadFixtures() === false) {
            return;
        }

        $dirFixtures = (string)realpath(__DIR__ . '/../../tests/Fixtures/');
        # we need to tell Shopware to load our custom fixtures
        # from our TEST autoload-dev area....
        $classLoader->addPsr4("MolliePayments\\Fixtures\\", $dirFixtures, true);
    }

    private function shouldLoadFixtures(): bool
    {
        $composerDevReqsInstalled = file_exists(__DIR__ . '/../../vendor/bin/phpunit');
        if ($composerDevReqsInstalled === false) {
            return false;
        }
        $dirFixtures = (string)realpath(__DIR__ . '/../../tests/Fixtures/');
        return is_dir($dirFixtures);
    }

    /**
     * @return void
     */
    public function prepareStorefrontBuild(): void
    {
        $pluginRoot = __DIR__ . '/../..';

        $distFileFolder = $pluginRoot . '/src/Resources/app/storefront/dist/storefront/js';

        if (!file_exists($distFileFolder)) {
            mkdir($distFileFolder, 0777, true);
        }

        if ($this->versionCompare->gte('6.5')) {
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
}
