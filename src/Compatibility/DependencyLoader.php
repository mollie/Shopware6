<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility;

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

        // load Flow Builder
        $loader->load('compatibility/flowbuilder/all_versions.xml');

        if ($this->versionCompare->gte('6.4.6.0')) {
            $loader->load('compatibility/flowbuilder/6.4.6.0.xml');
        }
    }

    public function prepareStorefrontBuild(): void
    {
        $pluginRoot = __DIR__ . '/../..';

        $distFileFolder = $pluginRoot . '/src/Resources/app/storefront/dist/storefront/js';

        if (! file_exists($distFileFolder)) {
            mkdir($distFileFolder, 0777, true);
        }

        if ($this->versionCompare->gte('6.5')) {
            $file = $pluginRoot . '/src/Resources/app/storefront/dist/mollie-payments-65.js';
            $target = $distFileFolder . '/mollie-payments.js';
        } else {
            $file = $pluginRoot . '/src/Resources/app/storefront/dist/mollie-payments-64.js';
            $target = $distFileFolder . '/mollie-payments.js';
        }

        if (file_exists($file) && ! file_exists($target)) {
            // while we use our current webpack approach
            // we must not use this.
            // also it's not perfectly working somehow
            // copy($file, $target);
        }
    }
}
