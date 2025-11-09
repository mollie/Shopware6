<?php
declare(strict_types=1);


use Shopware\Core\TestBootstrapper;
use Symfony\Component\Dotenv\Dotenv;
$_ENV['APP_ENV'] = 'test';
$_ENV['KERNEL_CLASS'] = Shopware\Core\Kernel::class;
$_ENV['APP_SECRET'] = '+g1fbgB/u0y45NSqftvvfvIksdBJUKSLjmxiNPDRyhGs6X+O625znsPHR0AUStElmDA21XOdn5lnwAoQR34Q5lamMXiUqn1DIT5LTHEVjtJ9CVUBX4FZwzldq9q6OmHDYjjXIV1P';

$projectDir = realpath(__DIR__ . '/../../../../');

$envFilePath = [
    $projectDir . '/.env',
    $projectDir . '/.env.local',
];

foreach ($envFilePath as $file) {
    if(!file_exists($file)) {
        continue;
    }
    (new Dotenv())->usePutenv()->bootEnv($file);
}

$dataBaseUrl = getenv('DATABASE_URL');


$testBootstrapper = new TestBootstrapper();
$testBootstrapper->setProjectDir($projectDir);
$testBootstrapper->setDatabaseUrl($dataBaseUrl);
$testBootstrapper->addActivePlugins('MolliePayments');
$testBootstrapper->setLoadEnvFile(false);
$testBootstrapper->bootstrap();

