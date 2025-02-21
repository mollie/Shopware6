## Easy Testing

Running Cypress tests is easy!
Just install it by using the built in makefile commands
and either open your tests in the Cypress UI, or run them directly from your CLI.

<!-- TOC -->

* [Easy Testing](#easy-testing)
    * [Installation](#installation)
    * [Cypress UI](#cypress-ui)
    * [Run in CLI](#run-in-cli)
    * [Tags](#tags)
    * [Before and BeforeEach](#before-and-beforeeach)
    * [TestRail Integration](#testrail-integration)
    * [Troubleshooting](#troubleshooting)

<!-- TOC -->

### Installation

This folder contains a `makefile` with all required commands.
Run the installation command to install Cypress and all its dependencies on your machine

```ruby 
make install
```

### Cypress UI

If you want to run your Cypress UI, just open it with the following command.
Please note, because this is an Open Source project, we cannot include a
shop URL in the configuration. Therefore you need to provide it on your own.
The tests might differ between Shopware versions, though the baseline is always the same.
So there is a parameter to tell Cypress what Shopware version should be tested.

```ruby 
make open-ui shopware=6.3 url=https://my-local-or-remote-domain 
```

### Run in CLI

You can also use the CLI command to run Cypress on your machine or directly in your build pipeline.
Cypress will then test your local or remote shop with the tests of the provided Shopware version.

```ruby 
make run shopware=6.x url=https://my-local-or-remote-domain 
```

### Tags

You can run a subsegment of tests by providing tags when running Cypress.
These tags need to exist in the title of a test.
We recommend the prefix @, su like '@core'.

```ruby 
make run shopware=6.x url=https://my-local-or-remote-domain tags='@core @smoke'
```

Here is a list of currently allowed tags.
Please use them if appropriate.

| Tag     | Description                                                                                                                         |
|---------|-------------------------------------------------------------------------------------------------------------------------------------|
| @core   | Indicates that the test does not require a Mollie API key. These tests will also run in the PR pipeline before something is merged. |
| @sanity | Just a few payment and flow tests to see if it still works.                                                                         |

### Before and BeforeEach

As mentioned, we use tags to filter the tests that should be run.

Some of our tests include a `before` and `beforeEach` hook with proesses such as API preparations and more.
Unfortunately there are technial limitiations when filtering tests with tags.

This means, that tests might not get executed, but their before hooks will still run and cost execution time.

Therefore, we try to create easy functions as **hooks** that will just be called inside the isolated tests.

Here is a full sample of a before and beforeEach that can be run and controlled within the scope of a single test.

```javascript
let beforeAllCalled = false;

// define hooks, or whatever you have to use (flexible but keep it simple)
// you can of course use other names, but it makes sense
function beforeEach() {
    if (!beforeAllCalled) {
        // ...code fore beforeAll...
        beforeAllCalled = true;
    }
    // ...
}

it('My Test', () => {
    // call hook before running the test
    beforeEach();
    // ...
})
```

### TestRail Integration

This Cypress project integrates with our TestRail project.
TestRail is a software to manage test cases keep track on their statuses.

You could, in theory, configure your own TestRail credentials (Cypress.env.json), but unfortunately the mapped test
cases
will not match your IDs, so it will probably not work. This is only for our QA team at the moment.

### Troubleshooting

Shopware 6.4.4.0 introduced LAX cookies.
The tests have been adjusted to work with that change, but you need to use HTTPS!
Once changed, it should all work as expected.