/// <reference types="vitest" />
import {createRequire} from 'node:module'
import {defineConfig} from 'vitest/config'

// allure-vitest is installed in dev/node_modules, but Vite resolves the reporter and the
// setup file from the plugin root, where they are not visible - and NODE_PATH does not
// apply to ESM. Resolve them from dev/ and hand Vite the absolute paths instead.
const requireFromDev = createRequire(new URL('../dev/package.json', import.meta.url))

export default defineConfig({
    test: {
        include: ['./src/Resources/app/**/*.spec.js'],
        watch: false,
        // Writes Allure results for the CI report, next to the PHP suites and Cypress.
        setupFiles: [
            requireFromDev.resolve('allure-vitest/setup'),
            // Tags every test with the bundle it belongs to, which is what the report tree
            // groups by. Resolved from dev/ for the same reason as the reporter above.
            requireFromDev.resolve('./vitest.setup.js'),
        ],
        reporters: [
            'default',
            [
                requireFromDev.resolve('allure-vitest/reporter'),
                {
                    resultsDir: './.reports/allure/results',
                    // The layer of the testing pyramid these tests belong to. Set on the
                    // reporter rather than in a hook so that a spec which dies in beforeAll
                    // is still counted - see config/allurerc.mjs for the other three.
                    globalLabels: {layer: 'Unit'},
                },
            ],
        ],
    },
})
