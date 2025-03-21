<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$projectDir = realpath(__DIR__ . '/../../../../../');
$pluginDir = realpath(__DIR__ . '/../../');

(new TestBootstrapper())
    ->setProjectDir($projectDir)
    ->setPlatformEmbedded(false)
    ->addCallingPlugin($pluginDir.'/composer.json')
    ->bootstrap();
