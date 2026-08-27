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

    // The report groups by layer and suite. Without both labels a PHPUnit test falls back
    // to its full namespace, and every branch of the tree then starts with the same
    // Mollie > Shopware > Unit prefix instead of the category the test belongs to.
    'lifecycleHooks' => [
        new class() implements BeforeTestStartHookInterface {
            /**
             * Test classes are named Mollie\Shopware\<Layer>\<Category>\...\SomeTest, so the
             * category is the fourth segment - the layer itself is already its own label.
             */
            private const CATEGORY_POSITION = 3;

            public function beforeTestStart(TestResult $test): void
            {
                $layer = getenv('ALLURE_LAYER');

                if (is_string($layer) && strlen($layer) > 0) {
                    $test->addLabels(Label::layer($layer));
                }

                $category = $this->readCategory($test->getFullName());

                if ($category !== null) {
                    $test->addLabels(Label::suite($category));
                }
            }

            private function readCategory(?string $fullName): ?string
            {
                if ($fullName === null) {
                    return null;
                }

                $className = explode('::', $fullName)[0];
                $segments = explode('\\', $className);

                // The last segment is the test class itself, so a test sitting directly in
                // its layer has no category to report.
                if (count($segments) <= self::CATEGORY_POSITION + 1) {
                    return null;
                }

                return $segments[self::CATEGORY_POSITION];
            }
        },
    ],
];
