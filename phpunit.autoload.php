<?php

include_once __DIR__ . '/vendor/autoload.php';

$classLoader = new \Composer\Autoload\ClassLoader();
$classLoader->addPsr4("Kiener\\MolliePayments\\", __DIR__ . '/src', true);
$classLoader->addPsr4("Kiener\\MolliePayments\\Tests\\", __DIR__ . '/tests', true);
$classLoader->register();