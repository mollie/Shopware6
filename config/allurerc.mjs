// Allure Report 3 config, used by the report job of the CI pipelines.
//
// Every CI stage - the unit tests on the runner, and integration, Behat and Cypress per
// Shopware version - is collected into its own dump and forced into its own environment.
// That keeps the same test from four Shopware versions side by side instead of collapsing
// into "retries" of a single result. The job passes the stages it actually found, so a new
// version in the build matrix does not have to be repeated here.
const environments = (process.env.ALLURE_ENVIRONMENTS ?? '')
    .split(';')
    .filter((entry) => entry.includes('='))
    .map((entry) => {
        const [id, ...name] = entry.split('=');

        // The matcher never fires: the environment is assigned by `allure run --environment`.
        // The entry exists so the report shows a readable name instead of the sanitised id.
        return [id, {name: name.join('='), matcher: () => false}];
    });

export default {
    name: 'Mollie Payments for Shopware 6',
    output: './.reports/allure/report',
    historyPath: './.reports/allure/history.jsonl',
    environments: Object.fromEntries(environments),
    plugins: {
        awesome: {
            options: {
                // Left alone, the tree groups by titlePath - whatever path the framework
                // happens to report. That is a PHP namespace for PHPUnit, a spec file path
                // for Cypress and Vitest, and nothing at all for Behat, which is why Behat
                // had no category. Group by what a test means instead: the layer it
                // exercises, then the category inside that layer. Both labels are set by
                // the integrations themselves - see config/allure.config.php, the Cypress
                // support file and dev/vitest.setup.js.
                groupBy: ['layer', 'suite'],
            },
        },
    },
};
