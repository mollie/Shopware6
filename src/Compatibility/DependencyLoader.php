<?php

namespace Kiener\MolliePayments\Compatibility;

use Psr\Container\ContainerInterface;
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

        } else if ($versionCompare->gte('6.3.5.0')) {

            $loader->load('compatibility/services_6.3.5.0.xml');

        }
    }

}
