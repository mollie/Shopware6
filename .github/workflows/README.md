The workflows contain the whole CI checks and E2E tests based on Github Actions.
If you want to setup these workflows in your repository, please follow these instructions.

### Enable Github Actions

Open your repository settings and make sure to allow Github Actions.

### Github Secrets

Add your TEST Api Key of your Mollie Account to your Github Secrets.
This is necessary to install the plugin in the Shopware test shops and run the E2E test suite.

If you use the Cypress-TestRail integration, please also configure that data as described.

| Secret | Value                                                                                         |
|--- |-----------------------------------------------------------------------------------------------|
| MOLLIE_APIKEY_TEST | Your Mollie TEST Api Key                                                                      |
| TESTRAIL_DOMAIN | The domain of the TestRail organization for the Cypress TestRail integration                  |
| TESTRAIL_USERNAME | A username of the TestRail organization for the Cypress TestRail integration                  |
| TESTRAIL_PASSWORD | The password for the user of the TestRail organization for the Cypress TestRail integration   |



