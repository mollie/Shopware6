// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'
import axios from "axios";

import 'cypress-axe'

// Reports every test, command and screenshot to the Allure results of this run.
import 'allure-cypress'
import * as allure from 'allure-js-commons'

const CypressFilters = require('cypress-filters');
new CypressFilters().register();


Cypress.on('uncaught:exception', (err, runnable) => {
    // returning false here prevents Cypress from
    // failing the test because some third party apps
    // cause an error in the console which stops the test
    return false
})


// The report groups by layer and suite. Without both labels a spec falls back to its file
// path, and every branch of the tree then starts with the same cypress > e2e prefix instead
// of the area the spec covers.
beforeEach(() => {
    allure.layer('E2E')

    // cypress/e2e/<area>/... - admin, api, store-api or storefront
    const area = Cypress.spec.relative.split('/')[2]

    if (area !== undefined) {
        allure.suite(area)
    }
})


afterEach(function () {
    // Stop after first failure only in UI mode (cypress open), not in headless mode (cypress run)
    if (this.currentTest.state === 'failed' && Cypress.config('isInteractive')) {
        Cypress.runner.stop()
    }
});