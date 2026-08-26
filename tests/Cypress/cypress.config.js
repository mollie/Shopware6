const {defineConfig} = require('cypress')
const {allureCypress} = require('allure-cypress/reporter')

// when running in parallel in Github with multiple instances
// it's somehow flaky...but this might only be because of a performance on the docker image?!
// it usually runs really good offline, so let's just try couple of retries in runMode for now
// and yes I know, this is not perfect...but it might work

// Cypress keeps a single handler per node event, but the Allure reporter, cypress-testrail
// and our own plugins file all want the same three. Fan those out to every consumer and
// merge the task objects, so registering Allure does not silently unhook TestRail.
const FANNED_OUT_EVENTS = ['before:run', 'after:run', 'after:spec']

function createEventMultiplexer(on) {
    const handlers = {}
    const tasks = {}

    const register = (event, argument) => {
        if (event === 'task') {
            Object.assign(tasks, argument)
            return
        }

        if (!FANNED_OUT_EVENTS.includes(event)) {
            // Everything else keeps its return value - before:browser:launch hands back the
            // launch options, file:preprocessor the compiled file - so it must not be wrapped.
            return on(event, argument)
        }

        if (!handlers[event]) {
            handlers[event] = []
            on(event, (...args) => Promise.all(handlers[event].map((handler) => handler(...args))))
        }

        handlers[event].push(argument)
    }

    return {register, flushTasks: () => on('task', tasks)}
}

module.exports = defineConfig({
    chromeWebSecurity: false,
    retries: {
        "runMode": 3,
        "openMode": 0
    },
    watchForFileChanges: false,
    trashAssetsBeforeRuns: true,
    screenshotOnRunFailure: true,
    video: false,
    videoCompression: 50,
    devices: [
        {
            key: 'desktop',
            name: 'Desktop',
            width: 1920,
            height: 1080,
        },
        {
            key: 'ipad-landscape',
            name: 'iPad (Landscape)',
            width: 1024,
            height: 768,
        },
    ],
    e2e: {

        defaultCommandTimeout: 8000,
        pageLoadTimeout: 30000,
        requestTimeout: 15000,
        responseTimeout: 15000,
        execTimeout: 60000,
        taskTimeout: 60000,

        testIsolation: true,

        // We've imported your old cypress plugins here.
        // You may want to clean this up later by importing these.
        setupNodeEvents(on, config) {
            const {register, flushTasks} = createEventMultiplexer(on)

            allureCypress(register, config, {
                resultsDir: 'allure-results',
                globalLabels: [{name: 'parentSuite', value: 'Cypress E2E'}],
            })

            const result = require('./cypress/plugins/index.js')(register, config)

            flushTasks()

            return result
        },
    },
})
