<?php
declare(strict_types=1);


use Shopware\Core\TestBootstrapper;
use Symfony\Component\Dotenv\Dotenv;
$_ENV['APP_ENV'] = 'test';
$_ENV['KERNEL_CLASS'] = Shopware\Core\Kernel::class;
$_ENV['APP_SECRET'] = '+g1fbgB/u0y45NSqftvvfvIksdBJUKSLjmxiNPDRyhGs6X+O625znsPHR0AUStElmDA21XOdn5lnwAoQR34Q5lamMXiUqn1DIT5LTHEVjtJ9CVUBX4FZwzldq9q6OmHDYjjXIV1P';

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

