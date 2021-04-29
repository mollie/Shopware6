/// <reference types="cypress" />
// ***********************************************************
// This example plugins/index.js can be used to load plugins
//
// You can change the location of this file or turn off loading
// the plugins file with the 'pluginsFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/plugins-guide
// ***********************************************************

// This function is called when a project is opened or re-opened (e.g. due to
// the project's config changing)


// promisified fs module
const fs = require('fs-extra')
const path = require('path')
const webpack = require('@cypress/webpack-preprocessor')


function getConfigurationByFile(file) {
    const pathToConfigFile = path.resolve('cypress', 'config', `${file}.json`)
    return fs.readJson(pathToConfigFile)
}


module.exports = (on, config) => {

    on('file:preprocessor', webpack({
        webpackOptions: require('../../webpack.config'),
        watchOptions: {},
    }))

    on('before:browser:launch', (browser = {}, launchOptions) => {
        if (browser.name === 'chrome' || browser.name === 'edge') {
            launchOptions.args.push('--disable-features=SameSiteByDefaultCookies')
            return launchOptions
        }
    })

    // accept a configFile value or use development by default
    return getConfigurationByFile(config.env.conf || 'dev')
}
