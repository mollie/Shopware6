
## Easy Testing

Running Cypress tests is easy!
Just install it by using the built in makefile commands
and either open your tests in the Cypress UI, or run them directly from your CLI.


### Installation

This folder contains a `makefile` with all required commands.
Run the installation command to install Cypress and all its dependencies on your machine

```ruby 
make install
```


### Cypress UI
If you want to run your Cypress UI, just open it with the following command.
Please note, because this is an Open Source project, we cannot include a
shop URL in the configuration. Thus you need to provide it on your own.
The tests might differ between Shopware versions, though the baseline is always the same.
So there is an additional parameter to tell Cypress what Shopware version should be tested.
This parameter is optional and its default is always the latest supported Shopware version.

```ruby 
make open-ui url=http://my-local-or-remote-domain

make open-ui url=http://my-local-or-remote-domain shopware=6.3
```

### Run in CLI
You can also use the CLI command to run Cypress on your machine or directly in your build pipeline.
Cypress will then test your local or remote shop with the tests of the provided Shopware version.

```ruby 
make run url=http://my-local-or-remote-domain shopware=6.x
```

