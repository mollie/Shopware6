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

        /** @var ContainerBuilder $containerBuilder */
        $containerBuilder = $this->container;

        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../Resources/config'));

        # load all our base services that
        # we need all the time
        $loader->load('services.xml');

        # now load our base compatibility services
        # that already wrap our functions for different shopware versions
        $loader->load('compatibility/base.xml');

        # now load our xml files that only work in specific shopware versions

        if (VersionCompare::gte($version, '6.4')) {

            $loader->load('compatibility/services_6.4.xml');

        } else if (VersionCompare::gte($version, '6.3')) {

            $loader->load('compatibility/services_6.3.xml');

        } else if (VersionCompare::gte($version, '6.2')) {

            $loader->load('compatibility/services_6.2.xml');
        }
    }

}
