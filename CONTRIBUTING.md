BE A PART OF IT!
=================

Giving the world something back is great!

If you would like to contribute and do something to improve this project, we'd be more than happy about it!

Here are some short guides on how to get started and what to consider when working on this project.

## Development Environment

### 1. Shopware Version

Please install the plugin in your Shopware 6 environment.
This can be anything that runs Shopware (local machine, Vagrant, dockware.io, ...)
Take care of the supported minimum version of this plugin.

It would be perfect to develop and test it on the latest version as well as on the minimum version.

### 2. Configure Domain

In order to be able to create payments with Mollie, you would need a domain that is NOT `localhost`.

The easiest way to do this, is to create a custom domain locally using the `/etc/hosts` file.

Open your `etc/hosts` file and append the line below with a custom domain that you would like to use:

```bash
127.0.0.1 your-domain-xyz.com
```

Now all your requests to this domain will be redirected to your localhost and passed on to the Shopware shop that matches your domain.



> This needs to be done only once!

### 3. Configure Shopware

Now that you have created your custom domain, please use it for your sales channels in Shopware.

If this has been done, you should be able to start the shop with the provided custom domain.

## Development

### 1. Installation

The plugin is not delivered with production dependencies.
You can install them with this makefile command in the plugin root directory.

```bash
make install
```

Afterwards, please run these Shopware commands in your DocRoot to build all required artefacts.
This will also automatically install the plugin before.
That is a required process within Shopware.

```bash
cd custom/plugins/MolliePayments && make build
```

Now all required JS/CSS distribution files have been built and you can simply continue using the plugin.

#### Plugin Configuration

Open the Shopware Administration and navigate to the plugin configuration.

Add your Mollie Account API keys in it and make sure to turn on TEST MODE (recommended).

Verify that the new Mollie payment methods are enabled in Shopware and also assign them to your Sales Channels.

Once this is done, you can just start with your first checkout in Shopware.

#### Build Plugin

We've added a small helper tool in the makefile.
This uses the Shopware default commands to rebuild the plugin and all its required distribution artifacts.

Use this, after modifying anything in the JS or SCSS files, to see those changes in the shop.

Please note, this requires the plugin to be existing and installed in a real Shopware 6 shop.

```bash
make build
```

#### Developer Tools

If you want to dig deeper into development, you might need some `DEVELOPER TOOLS`.
You can easily install them using this command from the makefile located in the root directory of the plugin.

```bash
make dev
```

These tools will be described later in this document.

#### Dev Fixtures

We have prepared all kinds of development data fixtures for you.
Subscription Products, Voucher Products and more.

To use these fixtures, please install and activate this FixturePlugin (https://github.com/basecom/FixturesPlugin).

Then simply install the "dev" dependencies of the Mollie plugin using `make dev` and run the following command.

```bash 
make fixtures
```

Congratulations, you should now see all kinds of products in your Shopware shop.

If you only want to install a few of the fixtures, feel free to use these commands directly

```bash 
# activates and assigns payment methods for your Sales Channels
php bin/console fixture:load:group mollie-setup

# Installs demo data such as categories, products and users
php bin/console fixture:load:group mollie-demodata
```

#### Local Webhooks

You should already be able to develop new features.

If you want the full experience, including webhooks locally on your machine, please see the corresponding guide on the WIKI page:
https://github.com/mollie/Shopware6/wiki/Webhooks

I promise - this is so much more fun when creating new features!


> Keep in mind, it's not always necessary!

### 2. Code Architecture

You might think that the architecture is a bit "wild". That is in fact the truth.
The plugin has been grown through the help of quite a lot of people.
We now try to improve the overall quality step by step.

You might now ask where to place your files and how to design your changes?
Please read this as a small guide:

#### Patterns and Principles

We try to avoid functions with nullables (arguments, returns) and optional arguments as much as possible.
Every function should have arguments and return values that are not misleading and do not need any special checks afterwards.
Try to use the fail-fast approach and use your functions as if they would just work...because they should.

In the migration phase, this might not be 100% possible each time, so please test as good as possible and better think twice :)

Here's a short list of things to consider:

* Fail-Fast
* Single Responsibility
* Early Returns

If you have questions, don't hesitate to contact us!

#### Compatibility Gateway

Unfortunately Shopware 6 is often quite different.
To somehow handle this, we've introduced a compatibility structure as a proof of concept.
In the folder `Compatibility` you see all kinds of things that might need some special treatment.

Either different classes, subscribers, ... due to breaking changes in interfaces,
or just a small gateway class to easily handle access to data that might need to be done in different ways.

So if a function is deprecated, not existing or just different in a Shopware version, try to create your own signature for this use-case, add it to the gateway and hide the different usages in that abstraction layer.

If you have problems on a level of Dependency Injection inside your XML file, please create a custom XML file in `Resources/config/compatibility` and only load the versions of your services that work for a specific Shopware version.
You can adjust what will be loaded in the DependencyLoader.php within the Compatiblity folder.

We don't know if this might grow too much in the future, but let's just give it a try!

### 4. Code Style

We are using PSR-1/PSR-2 Standard.
The PHPStorm standard code style should fit this standard.

To make it easy for you to use these standards, there are some make commands in the plugin root directory that you should use:

```bash
## Check for PHP Syntax errors
make phpcheck

## Check for PHP version and minimum compatibility
make phpmin 

## Starts the PHP CS Fixer (no-auto fixing -> please use PR command)
make csfix

## Starts the PHPStan Analyzer
make stan

## Starts the ESLint Analyzer Javacscript
make eslint

## Starts the Stylelint Analyzer for SCSS
make stylelint
```

## Testing

Testing is a must-have for us!

We have provided 3 tools for you.

There's a setup for `PHPUnit Tests`, `Jest Javascript Tests`, as well as a very easy `Cypress E2E Test Suite` that you can just run locally.

### PHPUnit

Please use this command to run your PHPUnit tests.

It is configured to include Code Coverage reports, so there will be a new report HTML file that you can open and use to improve your testing coverage.

```bash
make test
```

What are our requirements for PHP Unit Tests?

* Function Description:
  Sometimes tests might be a bit hard to understand, so we require an easy human-readable description what is really going on here!

* Testing Structure:
  Please avoid having every line set next to each other! Your test tells a story - please make sure a developer can easily understand that one.
  Use paragraphs or whatever is necessary to have a really beautiful and easy to understand testing code.

* Fakes or Mocks:
  We'd be happy if you already design your code with interfaces, so you can easily create real fake objects for your tests.
  If that's not possible, please use at least Mocks or Stubs for your tests.

### Jest

Please use this command to run your Jest tests.

It is configured to test everything in the folder Resources/app/storefront/tests

```bash
make jest
```

### Cypress E2E

If you open the `tests` folder and navigate to the `Cypress` directory, you will find another `makefile`.

This is your main makefile for everything related to Cypress. It helps you to get started as easy as possible.

```bash
# Install Cypress first
make install

# Open Cypress UI to easily view and create tests
make open-ui shopware=6.x.y.z url=https://your-domain-xxx.com

# Automatically run all E2E tests in your terminal
make run shopware=6.x.y.z url=https://your-domain-xxx.com
```

Creating Cypress tests is easy!
But please stick with the used `Keyword-Driven` Design Pattern with Actions and Object Repositories!
We try to avoid selectors and unstable click-routes directly within a test!

### Swagger API

If you want to use or test the API, then we have provided an easy to use Swagger setup for you.
Just use the integrated Swagger configuration.
The file contains all important requests that might be used and tested with the Mollie plugin.

You can simply start the Docker containers using the makefile in the folder tests/Swagger.
This creates a Docker container that can be reached with `http://localhost:8080`.

Additional instructions are available on this Swagger page that should be available now.

```ruby 
make run
```

## Pull Requests

### Prepare Pull Request

Our Github repository includes a pipeline that should test everything that could go wrong!
To make this process a bit easier for you, we've created a separate command in the makefile (plugin root) that prepares the whole code and checks it.

```bash
make pr
```

This command will not only run the analyzers and unit tests, but also start `PHP CS Fixer` in Auto-Fixing mode.
Please keep in mind to use this wisely and verify the changes made by this tool!

We also highly recommend to test your changes in the latest Shopware 6 version as well as in the minimum supported version.
At least we'd be happy about it :)

### PR Checklist

Before you create your Pull Request, here's a short check list for you:

* Tested locally?! (also with Shopware minimum Version?)
* Unit Tests created where appropriate?
* "make pr" command passes?

### Create Pull Request

If everything passed, push your changes to your fork and create a Pull Request on Github.

Let us know `WHY` you need these changes and `WHAT` you actually did!

If everything seems fine, we'd be happy to merge your changes and add it to an upcoming and official release!

THANKS FOR BEING A PART OF THIS!