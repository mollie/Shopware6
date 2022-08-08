const {defineConfig} = require('cypress')

module.exports = defineConfig({
    chromeWebSecurity: false,
    retries: {
        "runMode": 1,
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
            userAgent: '',
        },
        {
            key: 'ipad-landscape',
            name: 'iPad (Landscape)',
            width: 1024,
            height: 768,
            userAgent: 'Mozilla/5.0 (iPad; CPU OS 5_1 like Mac OS X) AppleWebKit/534.46 (KHTML, like Gecko) Version/5.1 Mobile/9B176 Safari/7534.48.3',
        },
    ],
    e2e: {
        experimentalSessionAndOrigin: true,
        // We've imported your old cypress plugins here.
        // You may want to clean this up later by importing these.
        setupNodeEvents(on, config) {
            return require('./cypress/plugins/index.js')(on, config)
        },
    },
})
