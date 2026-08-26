<?php

declare(strict_types=1);

use Qameta\Allure\Hook\BeforeTestStartHookInterface;
use Qameta\Allure\Model\Label;
use Qameta\Allure\Model\TestResult;

// Both PHPUnit suites share this file, so the values that differ between them come from
// the environment: the unit suite runs on the CI runner, the integration suite inside the
// Shopware container, and each has to write into its own results directory.
return [
    'outputDirectory' => getenv('ALLURE_OUTPUT_DIRECTORY') ?: '.reports/allure/results',

    // Without this the report only knows the test class. The label is what puts unit and
    // integration tests into separate branches of the report tree, next to Behat and Cypress.
    'lifecycleHooks' => [
        new class() implements BeforeTestStartHookInterface {
            public function beforeTestStart(TestResult $test): void
            {
                $layer = getenv('ALLURE_LAYER');

                if (!is_string($layer) || strlen($layer) === 0) {
                    return;
                }

                $test->addLabels(Label::parentSuite($layer));
            }
        },
    ],
];
