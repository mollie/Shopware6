<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$projectDir = realpath(__DIR__ . '/../../../../../');
$pluginDir = realpath(__DIR__ . '/../../');

(new TestBootstrapper())
    ->setProjectDir($projectDir)
    ->addCallingPlugin($pluginDir.'/composer.json')
    ->bootstrap();
