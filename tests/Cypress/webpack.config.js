var path = require('path')

module.exports = {
   resolve: {
       alias: {
           Scenarios: path.resolve(__dirname, 'cypress/support/scenarios'),
           Actions: path.resolve(__dirname, 'cypress/support/actions'),
           Repositories: path.resolve(__dirname, 'cypress/support/repositories'),
           Services: path.resolve(__dirname, 'cypress/support/services'),
           Fixtures: path.resolve(__dirname, 'cypress/fixtures'),
           Plugins: path.resolve(__dirname, 'cypress/plugins')
       }
   }
}
