# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased] 

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
