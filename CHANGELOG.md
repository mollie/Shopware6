# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased] 

### Added

- Returns for Shopware Commercial plugins are now transferred to Mollie when the return status is set to "Done" and can be canceled with the "Cancelled" status. Please note that refunds cannot be canceled after two hours.

### Changed

- The minimum supported Shopware version is now 6.4.5.0.
- Added a new Monolog channel "mollie." You can now add custom handlers and assign them to the Mollie channel.

### Fixed

- Fixed order details in the refund manager for Shopware 6.4.x.
- Resolved an issue with SwagCustomizedProducts where prices for option values are now correctly added to the order.
