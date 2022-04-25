
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

### Troubleshooting

Shopware 6.4.4.0 introduced LAX cookies.
The tests have been adjusted to work with that change, but you need to use HTTPS!
Once changed, it should all work as expected.