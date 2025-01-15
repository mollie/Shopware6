<?php


require_once __DIR__ . '/functions.php';

$testSuite = getArgument('testsuite');
$testSuiteBootstrapFileName = sprintf('%s/%s.php', __DIR__, $testSuite);

if (is_file($testSuiteBootstrapFileName)) {
    require_once $testSuiteBootstrapFileName;
    return;
}

require_once __DIR__ . '/../../vendor/autoload.php';

