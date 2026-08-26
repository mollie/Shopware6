/// <reference types="vitest" />
import {defineConfig} from 'vitest/config'

export default defineConfig({
    test: {
        include: ['./src/Resources/app/**/*.spec.js'],
        watch: false,
        // Writes Allure results for the CI report, next to the PHP suites and Cypress.
        setupFiles: ['allure-vitest/setup'],
        reporters: [
            'default',
            ['allure-vitest/reporter', {resultsDir: './.reports/allure/results'}],
        ],
    },
})