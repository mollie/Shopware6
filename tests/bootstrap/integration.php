<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$projectDir = realpath(__DIR__ . '/../../../../../');

$loader = (new TestBootstrapper())
    ->setProjectDir($projectDir)
    ->setPlatformEmbedded(false)
    ->addCallingPlugin()
    ->addActivePlugins('MolliePayments')
    ->setForceInstallPlugins(true)
    ->bootstrap()
    ->getClassLoader();


$loader->addPsr4('Kiener\\MolliePayments\\', __DIR__ . '/../../src/');
$loader->addPsr4('Mollie\\Shopware\\', __DIR__ . '/../../shopware/');
$loader->addPsr4("Shopware\\Core\\", __DIR__ . '/../../polyfill/Shopware/Core/');
$loader->addPsr4('Mollie\\Integration\\', __DIR__ . '/../Integration/');
