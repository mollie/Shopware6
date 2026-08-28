// Allure Report 3 config, used by the report job of the CI pipelines.
//
// Every CI stage - the unit tests on the runner, and integration, Behat and Cypress per
// Shopware version - is collected into its own dump and forced into its own environment.
// That keeps the same test from four Shopware versions side by side instead of collapsing
// into "retries" of a single result. The job passes the stages it actually found, so a new
// version in the build matrix does not have to be repeated here.
//
// Only the testing pyramid is configured here, but doing that replaces the whole chart
// list, so Allure's own defaults are imported and the one entry is swapped out. The import
// is why the report job installs Allure into the checkout instead of globally.
import {defaultChartsConfig} from '@allurereport/charts-api';

const environments = (process.env.ALLURE_ENVIRONMENTS ?? '')
    .split(';')
    .filter((entry) => entry.includes('='))
    .map((entry) => {
        const [id, ...name] = entry.split('=');

        // The matcher never fires: the environment is assigned by `allure run --environment`.
        // The entry exists so the report shows a readable name instead of the sanitised id.
        return [id, {name: name.join('='), matcher: () => false}];
    });

// The layers of the testing pyramid, base first - a test whose layer is not listed here is
// left out of the chart altogether, which is what happened to everything but Cypress while
// the suites reported free-form layer names. Each suite tags itself with one of these:
// PHPUnit in config/allure.config.php, Cypress in tests/Cypress/cypress.config.js, Vitest
// in config/vitest.config.ts, Behat through defaultLabels below. Behat counts as Functional
// rather than E2E because it drives the controllers, not a browser.
const LAYERS = ['Unit', 'Integration', 'Functional', 'E2E'];

export default {
    name: 'Mollie Payments for Shopware 6',
    output: './.reports/allure/report',
    historyPath: './.reports/allure/history.jsonl',
    environments: Object.fromEntries(environments),

    // Behat reports through Cucumber JSON, and that format has nowhere to put a label, so
    // its layer is filled in here. Every other suite attaches its own layer to every
    // result it writes, so this default only ever applies to Behat.
    defaultLabels: {layer: 'Functional'},

    plugins: {
        awesome: {
            options: {
                // Left alone, the tree groups by titlePath - whatever path the framework
                // happens to report. That is a PHP namespace for PHPUnit, a spec file path
                // for Cypress and Vitest, and nothing at all for Behat, which is why Behat
                // had no category. Group by what a test means instead: the layer it
                // exercises, then the feature inside that layer.
                //
                // It has to be `feature` and not `suite`: allure-cypress writes a suite
                // label of its own for every describe() level, and a test carrying two
                // suite labels is filed under both - which is where categories like
                // "Desktop (1920x1080)" came from. Nothing populates `feature` by itself
                // except the Cucumber reader, and there it is the Behat feature name,
                // which is exactly the category Behat should be filed under.
                groupBy: ['layer', 'feature'],
                charts: defaultChartsConfig.map((chart) =>
                    chart.type === 'testingPyramid' ? {...chart, layers: LAYERS} : chart,
                ),
            },
        },
    },
};
