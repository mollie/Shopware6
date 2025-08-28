<?php
declare(strict_types=1);


use Shopware\Core\TestBootstrapper;
use Symfony\Component\Dotenv\Dotenv;

$projectDir = realpath(__DIR__ . '/../../../../');

$envFilePath = $projectDir . '/.env';

if (\is_file($envFilePath) || \is_file($envFilePath . '.dist') || \is_file($envFilePath . '.local.php')) {
    (new Dotenv())->usePutenv()->bootEnv($envFilePath);
}
$dataBaseUrl = getenv('DATABASE_URL');


$testBootstrapper = new TestBootstrapper();
$testBootstrapper->setProjectDir($projectDir);
$testBootstrapper->setDatabaseUrl($dataBaseUrl);
$testBootstrapper->addActivePlugins('MolliePayments');
$testBootstrapper->bootstrap();

