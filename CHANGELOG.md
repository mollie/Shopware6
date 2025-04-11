# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- Added Norwegian language support
- Added Swedish language support
- Added Polish language support
- Added Czech language support
- Added Slovenian language support
- Added Hungarian language support

### Changed
- Removed typehints for EntityRepository in order to allow repository decoration in Shopware 6.4

### Fixed
- Fixed the error "Call to a member function info() on null"
- Fixed the issue, that a wrong API Key was used when lineitems were cancelled in administration


## [4.15.0] - 2025-04-03

### Added
- Swish payment method is now available for Mollie Payments.

### Changed
- Previously a cancellation of an express checkout led to restoring the initial cart. This will not happen anymore if the previous cart was empty. Therefore, the product from the cancelled express checkout will now remain in the cart.
- Improve the way how express checkouts (Apple Pay Direct, PayPal Express) backup and restore carts on cancellation.
- Full refunds do now take already pending (partial) refunds into account. It's now way easier to also refund the rest amount of an order.
- The Administration and Storefront NPM Dev-Dependencies that we use for testing have been moved to a location that Shopware is not using. This should speed up a lot when you develop a shop where the Mollie plugin is installed. 
- The subscription page in the account has been updated to comply with WCAG standards.

### Fixed
- Fixed an issue with transitions at too early webhook calls from Mollie
- Fix problem with broken PayPal Express checkout in combination with some rare PayPal addresses.
- Fix problem where it was possible to get stuck in PayPal Express mode after cancelling the authorization.
- Fix problems with PayPal Express flows where carts were suddenly missing or not correctly restored.
- Fix problem in PayPal Express (JavaScript) where the checkout was already initialized before the product was correctly added to the cart.
- Fixed the issue with saved Credit Card. If you paid first with a different payment method and this payment is failed, the next attempt with Credit Card and saved token failed everytime.

## [4.14.1] - 2025-02-03

### Fixed
- Fixed scheduled tasks

## [4.14.0] - 2025-02-03
### Added
- Returns for Shopware Commercial plugins are now transferred to Mollie when the return status is set to "Done" and can be canceled with the "Cancelled" status. Please note that refunds cannot be canceled after two hours.
- MB Way payment method is now available for Mollie Payments.
- Multibanco payment method is now available for Mollie Payments.
- Added Portuguese translation
- Added Spanish translation

### Changed

- The minimum supported Shopware version is now 6.4.5.0.
- Added a new Monolog channel "mollie." You can now add custom handlers and assign them to the Mollie channel.
- When a webhook from mollie is sent too early to the shop, a debug message is logged instead of a warning.

### Fixed

- Fixed order details in the refund manager for Shopware 6.4.x.
- Resolved an issue with SwagCustomizedProducts where prices for option values are now correctly added to the order.
- Fixed the issue with OrderNotFoundException. This class was removed by shopware in 6.5.0 and it is not used within the plugin anymore
- Fixed compatibility with the Shopware B2B Suite Plugin
