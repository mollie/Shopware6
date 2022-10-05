const {defineConfig} = require('cypress')

// when running in parallel in Github with multiple instances
// it's somehow flaky...but this might only be because of a performance on the docker image?!
// it usually runs really good offline, so let's just try couple of retries in runMode for now
// and yes I know, this is not perfect...but it might work

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
        experimentalSessionAndOrigin: true,
        testIsolation: "strict",
        experimentalWebKitSupport: true,
        // We've imported your old cypress plugins here.
        // You may want to clean this up later by importing these.
        setupNodeEvents(on, config) {
            return require('./cypress/plugins/index.js')(on, config)
        },
    },
})
