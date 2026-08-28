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


// The report groups by layer and feature. The layer is set on the reporter in
// cypress.config.js; the feature is per spec, so it has to be set here.
//
// It has to be `feature` and not `suite`: allure-cypress already writes a suite label for
// every describe() level of the spec, and a test carrying two suite labels is filed under
// both - which is how categories like "Desktop (1920x1080)" and "POST /payment/update"
// ended up next to the real ones. The folder a spec sits in is the category instead, so a
// new spec is filed correctly without anyone having to name its describe blocks a certain
// way.
beforeEach(() => {
    // cypress/e2e/<...>/<spec>.cy.js - the innermost folder, so checkout, payment-methods,
    // subscriptions, store-api and so on.
    const segments = Cypress.spec.relative.split('/')
    const folder = segments[segments.length - 2]

    if (folder !== undefined) {
        allure.feature(folder)
    }
})


afterEach(function () {
    // Stop after first failure only in UI mode (cypress open), not in headless mode (cypress run)
    if (this.currentTest.state === 'failed' && Cypress.config('isInteractive')) {
        Cypress.runner.stop()
    }
});