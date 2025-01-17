# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased] 


### Features
- Returns over Shopware Commercial plugins is now transferred to Mollie when the Return status is set to "Done" and can be cancelled with the "Cancelled" status. Please note that the refund cannot be cancelled after two hours.
### Changes
- Minimum Supported Shopware version is now 6.4.5.0
- Add new monolog channel "mollie". You can now add custom handler and assign them to the mollie channel
### Fixes
- Fixed order details in refund manager in shopware 6.4.x
- Fixed the issue for SwagCustomizedProducts, the prices for option values are now added to the order