## [unreleased]
- Fixed wrong total amount in Apple Pay Direct when using NET display prices for customer groups. Taxes were not added in this case.
- Fix a problem where the credit card input fields are sometimes not editable due to race conditions when loading the mollie.js file. (removed defer-async loading).
- When a customer changes the payment method of a subscription, all older payments that can still be canceled will be automatically canceled.
- Added support for the “Additional Options/Warranties” plugin.
- Added an admin overview page allowing existing subscribers to cancel their subscriptions.
- Fixed the display of payment methods in older Shopware versions.
- Fixed the “Test API Keys” button in the plugin settings for Shopware 6.7.
- Adjusted the payment status action based on the Shopware version to ensure compatibility with older versions.

## 4.21.0
- Shipping costs are now considered in refunds via Shopware Return Management.
- Fixed: Issue with carts containing multiple tax rates in combination with a promotion using proportional tax calculation.
- Updated: Corrected the documentation for the endpoint used to validate and create an Apple Pay payment session.
- Fixed: Shipping methods were shown in Apple Pay Express even for countries where shipping was disabled in the administration.
- Removed: Removed dependency for the Basecom Fixture plugin and built our own fixture framework.
- Fixed: MolliePaymentMethodAvailabilityRemover to consider carts with price 0 to avoid getting all the payment methods removed.
– Compatibility with Click & Collect plugin
- Fixed: Descriptions of payment methods were displayed during checkout even when they were not selected.
- The profile navigation has been extended to include management of saved credit card details (visible only when credit card data is available).
- Tracking parameters are now optional for all shipping API routes.

# 4.20.1
- Fixed: Order overview in Shopware 6.5 does not crash anymore
# 4.20.0
- Changed order builder to use order addresses instead of customer default addresses to make sure the address in mollie matches the order information in shopware.
- Fixed an issue where Apple Pay Direct did not work when the phone number was configured as a required field in the shop.
- Fixed compatibility with shopware commercial 
- Fixed: Resolved an issue where Mollie data was shown in the admin order view even when the final transaction was not processed via Mollie.
- Shopware Refunds now correctly applies the refunded amount.
- Title in the Admin Configuration was fixed 

## [4.19.0] - 2025-10-09
- Added Estonian Language Support
- Added Greek Language Support
- Added Croatian language support
- Added Icelandic language support
- Added Lithuanian language support
- Added Latvian language support
- Added Romanian language support
- added Slovak language support
- PayByBank is now available for subscriptions.
- Fixed a bug preventing subscriptions from being restarted when the next payment date was in the future.

## [4.18.0] - 2025-09-08
### Added
- Bizum Payment method is now available for Mollie Payments

### Changed
- Order and Payment status is now changed only over webhooks, this way we prevent that the status is changed twice when the customer redirected back to shop and webhook is executed at the exact same time. If you have a test system which do not accept webhooks from outside, please set the environment variable MOLLIE_DEV_MODE=1
- The Finalize Action now uses the SalesChannel from the Order. However, in some cases, the SalesChannel is not set correctly, which can result in incorrect API keys being used during the Finalize Action
- Modified polyfill classes to ensure they are only loaded if they no longer exist in Shopware
- twig variable "formCheckInputClass" added to payment methods
- credit card payment method is now rendered through twig instead of javascript

### Fixed
- Fixed the doctrine parameter types in elastic search and migrations
- Fixed logging if automatic shipment didn't work
- Fixed saving Credit Card information
- Fixed Payment Method route in store-api
- Fixed config assignment for the refund manager
- Fixed last remaining subscription times being reset when paused and resumed
- Fixed the error that the storefront/dist folder does not exists
- Fixed automatic delivery when the tracking codes are just empty strings

## [4.17.0] - 2025-08-04
### Added
- Show validation errors when a guest account is created from Express Checkout e.g. Paypal Express or Apple Pay Direct

### Changed
- Refundmanager is now disabled for orders in Authorized state, it is not possible to refund an order if nothing was captured yet
- Changed the position of pending refunds in Refundmanager

### Fixed
- Fixed webhook, they were executed at same time as redirect back to shop, which changed the payment status twice
- Fixed language of error labels in payment forms
- Fixed order cloning when a subscription is renewed
- Fixed the display of refunded items in refund manager, while the refund is pending
- Fixed button styling in Refund Manager
- Fixed division by zero error for promotions without amount

## [4.16.0] - 2025-06-23
### Added
- Added Compatibility with Shopware 6.7
- Added Norwegian language support
- Added Swedish language support
- Added Polish language support
- Added Czech language support
- Added Slovenian language support
- Added Hungarian language support
- Added Finnish language support
- Added Danish language support
- Added orderId to JSON response of the Apple Pay Direct pay route in the Store API

### Changed
- Removed typehints for EntityRepository in order to allow repository decoration in Shopware 6.4
- Bank transfer payments are now set to the 'In Progress' state instead of 'Unconfirmed', as these payment methods require several days to process and should not be modified
- Mandates for Mollie Customers are not loaded if the customer was deleted in Mollie dashboard
- Removed "Webhook too early" functionality by fixing updatePayment race conditions in a different way. Webhook updates are now faster again
- The payment status "open" is now valid again for credit cards. In previous flows this was not possible and thus a problem, but due to new async. flows, this is now by design a valid way
- Remove logs in PaymentMethodRemover that lead to filling up the log files and disk space if Symfony handles requests for assets like CSS or images
- Increased minimum PHP Version to 8.0

### Fixed
- Fixed the error "Call to a member function info() on null"
- Fixed the issue, that a wrong API Key was used when lineitems were cancelled in administration
- Fixed the issue that the payment method of a paypal express transaction was changed to paypal over webhooks

## [4.15.0] - 2025-03-04
### Added
- Swish payment method is now available for Mollie Payments

### Changed
- Previously a cancellation of an express checkout led to restoring the initial cart. This will not happen anymore if the previous cart was empty. Therefore, the product from the cancelled express checkout will now remain in the cart
- Improve the way how express checkouts (Apple Pay Direct, PayPal Express) backup and restore carts on cancellation
- Full refunds do now take already pending (partial) refunds into account. It's now way easier to also refund the rest amount of an order
- The Administration and Storefront NPM Dev-Dependencies that we use for testing have been moved to a location that Shopware is not using. This should speed up a lot when you develop a shop where the Mollie plugin is installed
- The subscription page in the account has been updated to comply with WCAG standards

### Fixed
- Fixed an issue with transitions at too early webhook calls from Mollie
- Fix problem with broken PayPal Express checkout in combination with some rare PayPal addresses
- Fix problem where it was possible to get stuck in PayPal Express mode after cancelling the authorization
- Fix problems with PayPal Express flows where carts were suddenly missing or not correctly restored
- Fix problem in PayPal Express (JavaScript) where the checkout was already initialized before the product was correctly added to the cart
- Fixed the issue with saved Credit Card. If you paid first with a different payment method and this payment is failed, the next attempt with Credit Card and saved token failed everytime

## [4.14.1] - 2025-02-03
### Fixed
- Fixed scheduled tasks

## [4.14.0] - 2025-02-03
### Added
- Returns for Shopware Commercial plugins are now transferred to Mollie when the return status is set to "Done" and can be canceled with the "Cancelled" status. Please note that refunds cannot be canceled after two hours
- MB Way payment method is now available for Mollie Payments
- Multibanco payment method is now available for Mollie Payments
- Added Portuguese translation
- Added Spanish translation

### Changed
- The minimum supported Shopware version is now 6.4.5.0
- Added a new Monolog channel "mollie." You can now add custom handlers and assign them to the Mollie channel
- When a webhook from mollie is sent too early to the shop, a debug message is logged instead of a warning

### Fixed
- Fixed order details in the refund manager for Shopware 6.4.x
- Resolved an issue with SwagCustomizedProducts where prices for option values are now correctly added to the order
- Fixed the issue with OrderNotFoundException. This class was removed by shopware in 6.5.0 and it is not used within the plugin anymore
- Fixed compatibility with the Shopware B2B Suite Plugin

## [4.13.0] - 2024-12-17
### Features
- The payment method Trustly can now be used for subscriptions

### Improvements
- The number of Ajax calls on the order details page in the administration has been reduced
- The payment status is now set to "Unconfirmed" instead of "In Progress." This allows customers to complete their orders even if they closed the payment provider's page or used the browser back button
- Webhooks are now accepted only two minutes after order creation. This reduces the risk of the webhook updating the order status before the order is completed in the shop
- Automatic expiration now ignores orders if the most recent payment method is not a Mollie payment
- The Billie payment method is now hidden if no company name is provided in the billing address
- When shipping or canceling items, the shipping costs are marked as "shipped" for Klarna payments
- When shipping through Mollie, invalid **tracking codes** are now ignored. This ensures that the order is still marked as "shipped," even if the tracking information is invalid

### Fixes
- Apple Pay: Guest accounts are now reused for the same email address
- Fixed an issue with automatic expiration and bank transfer payments. Bank transfer payments were previously canceled too early. Now they are canceled after 100 days. This can be adjusted in the plugin configuration

## [4.12.1] - 2024-11-14
### Hotfix
- Compatibility with Shopware 6.6.8.x has been fixed
- The data protection checkbox is hidden when Apple Pay Direct is not available in the browser

## [4.12.0] - 2024-11-11
### Features
- PayPal Express is now available for beta testers
- The new payment method "PayByBank" is now available

### Improvements
- Autoloading of Shopware compatibility files is now during plugin runtime
- Credit notes can now be created for refunds with custom amounts
- Italian translation added to the configuration
- More detailed log messages added for status changes
- The Apple Pay payment method is now hidden in the shopping cart when displaying shipping details if Apple Pay is not available in the browser

### Deprecations
- The Apple Pay headless route `/mollie/applepay/add-product` is now deprecated. Please use the default Shopware `addToCart` route. If you wish to temporarily store the current user's cart and pay only for the current product (e.g., direct checkout from the product or category page), add the parameter `isExpressCheckout=1` to your `addToCart` route request. After checkout, the original cart will be restored

### Fixes
- Custom products with configured extra amounts are now correctly added to checkout
- Custom products cannot be purchased via Apple Pay direct until all required fields are filled

## [4.11.2] - 2024-10-17
### Hotfix
- Fixed compatibility issues with Shopware 6.6.7.0

## [4.11.1] - 2024-10-09
### Hotfix
- The "Add to Cart" button on the product detail page works again when Apple Pay Direct is enabled, and the privacy policy must be accepted via a checkbox
- Creating orders in the administration works again

## [4.11.0] - 2024-10-08
### Features
- Credit notes can be created during refunds
- The payment method "Billie" is only shown for business customers
- Subscription orders have a custom tag
- Apple Pay Direct: If GDPR is enabled in the administration, additional checkboxes are shown above the buttons
- Apple Pay Direct: The selector for finding and hiding Apple Pay Direct buttons in JavaScript was changed to improve usage with custom themes
- Apple Pay Direct is now compatible with the Shopware Custom Product plugin
- The Refund Manager is only available if the order contains refundable items

### Improvements
- Installing the Mollie plugin via Composer no longer shows the error that the "dist" folder does not exist
- Apple Pay Direct finds the correct shipping method if the customer changes the address within the Apple Pay overlay
- Customers can be created at Mollie with different profiles in different sales channels
- Added Italian translation to the administration

### Fixes
- Fixed the issue where, in some cases, the webhook from Apple Pay Direct was triggered faster than the order update in Shopware
- Added missing MailActionInterface for Shopware 6.4

## [4.10.2] - 2024-09-27
### Hotfix
- Fixed problems with missing code for automatic delivery
- Added more logs for tracking information
- Made sure that delivery informations are sent to mollie even with missing code
- Automatic expire of orders can now be deactivated in plugin configuration
- Automatic expire finds all orders with the payment status "in progress" from the past two months and sets them to cancel if the order creation time is after the configuration payment link expiration duration

## [4.10.1] - 2024-09-05
### Hotfix
- The memory usage issue in the newly scheduled task "mollie.order_status.expire" has been fixed
- Issues with marking the order as shipped have been resolved

## [4.10.0] - 2024-08-28
### Features
- New payment method "Riverty" is now available
- New payment method "Satispay" is now available
- New payment method "Payconiq" is now available
- Introduced a new event: SubscriptionCartItemAddedEvent. This allows you to implement custom logic when a subscription item is added to the cart
- Added Italian translations

### Improvements
- Apple Pay Direct now requests a phone number if the phone field is enabled in the Administration
- Apple Pay Direct guest accounts will now be reused instead of being created each time
- The ElasticSearch Indexer is now compatible with the MolliePayments plugin
- When using the "Ship through Mollie" button, you can now input a full URL in the code input field. The URL will automatically be extracted from the code
- Orders stuck in the "in progress" state will now be canceled if the payment link expires in Shopware. You can configure the expiration time in the Shopware Cart Settings

### Bug Fixes
- Orders can now be created when image names contain special query values, e.g., `product.png?width={width}`
- Fixed an issue where polyfill classes were incorrectly loaded
- Fixed an issue where delivery states were not loaded correctly, causing problems with automatic shipment

## [4.9.3] - 2024-07-04
### Hotfix
- Apple Pay direct is working again if the phone number is not required in registration

## [4.9.2] - 2024-07-03
### Features
- New payment method "Trustly" is now available
- New payment method "Payconiq" is now available

### Improvements
- Reduced the amount of requests to the database when loading configuration data

### Fixes
- Creating an order has been fixed. If a SalesChannel had an invalid localization, it resulted in errors

## [4.9.1] - 2024-06-27
### Features
- Giropay is discontinued and will not be activated after update. Please deactivate the payment method and remove it from saleschannel

### Improvements
- Apple Pay Direct: phone number is requested during checkout, if the phone number is required in shopware configuration

### Fixes
- Line Items are shown again in non mollie orders
- Apple Pay Direct: verification also works for domain with special characters
- Apple Pay Direct: shipping methods now take availability rules into account

## [4.9.0] - 2024-06-25
### Features
- In preparation of full iDeal 2.0 readiness we have removed the bank/issuer selection in the checkout. This is done to guarantee the most convenient experience possible for the shopper
- Authorized Klarna products can now be cancelled within shopware order
- OpenApi definition were added. Plugin Routes are now shown within Shopware Swagger

### Fixes
- Polyfill classes are now loaded with correct namespace

## [4.8.1] - 2024-05-23
### Hotfix
- The credit card input fields in Shopware 6.6 have been fixed

## [4.8.0] - 2024-05-21
### Features
- New payment method "Alma" is now available
- New payment method "MyBank" is now available
- New payment method "Bancomat Pay" is now available

### Improvements
- Improved compatibility with the Plugin AcrisPersistentCart

### Bug Fixes
- Fixed a storefront javascript warning on pages without offcanvas
- Fixed a problem with creating orders and imageUrls. When a product had a special character in the product image file, the order could not be created
- Fixed the definition of the css class "d-none". It does apply now only within Mollie classes and not globally

## [4.7.2] - 2024-04-30
### Hotfix
- Compatibility with Klarna payment plugin has been fixed
- SnippetFileInterface has been provided afterwards

## [4.7.1] - 2024-04-30
### Hotfix
- Fixed problems with routes
- CSS-Class d-none is applied only for mollie components

## [4.7.0] - 2024-04-29
### Features
- Added compatibility with Shopware 6.6
- Support for Shopware 6.4.0.0 has been discontinued; the new minimum version is 6.4.1.0
- Additional checkbox in the refund manager. There is now the option to refund taxes for net orders

### Improvements
- Loading of the mollie-payments.js has been optimized

### Bug Fixes
- Fixed Polyfill classes for Shopware 6.4.20.2. The Flowbuilder had errors when flag FEATURE_NEXT_17858 was active

## [4.6.0] - 2024-03-26
### Features
- New payment method Klarna One available in UK. Mollie's availability rules for payment methods in plugin settings can show or hide the method method for each customer

### Improvements
- Optimized ACL behavior for admin users with less privileges. Plugin does not require system:config:read permissions anymore
- Mollie JS file is not loaded on every storefront page now, only when it is actually needed
- Apple Pay can now be selected as default payment method in account area in other browsers than safari
- Apple Pay Direct guest account creation uses shopware default behavior and settings from the administration. For example bind customers to Sales Channel
- Apple Pay Direct shipping methods now uses shopware default behavior

### Bug Fixes
- Fixed problem with saving payment methods in admin if the system language of shopware was changed to something different than en-GB
- Fixed typo in "OrderAware" compatibility class for older shopware version

## [4.5.0] - 2024-02-19
### Features
- New payment method "Blik" is now available for the currency Zloty
- "Mollie Limits" has been expanded and renamed to "Mollie Availability Rules". When this option in the plugin is activated, all payment methods that are not active in the Mollie dashboard are hidden. Additionally, payment methods are hidden if the following rules apply:
  - Minimum value in the shopping cart is not reached
  - Maximum value in the shopping cart is exceeded
  - Only predefined currencies are allowed
  - Only allowed for predefined billing addresses

### Improvements
- Shopware cache now considers the value of the shopping cart, currency changes, and delivery address when listing payment methods

### Fixes
- When purchasing a subscription, the user was logged in as a guest after registration. This has now been fixed

## [4.4.2] - 2024-01-24
- Compatibility with new Shopware version 6.5.8.2

## [4.4.1] - 2024-01-22
### Hotfix
- Support the new technical names of the payment methods in Shopware. These should be automatically added for you on plugin updates/installing

## [4.4.0] - 2024-01-18
### Features
- Added a new feature for multiple items shipment. You are now able to mark multiple items as shipped in the Shopware administration, and this information will be sent to Mollie.
- Add new plugin configuration option to specify the duration for which log messages should be retained.

### Improvements
- Support the new technical names of the payment methods in Shopware. These should be automatically added for you on plugin updates/installing
- When stock management is disabled in Shopware, the Refund Manager no longer increases product stocks.
- Mollie bank information is now saved in order customFields.
- Minor performance fixes on the checkout page were added.
- Automatic shipment and flow builder action will now send tracking information to Mollie when a tracking code is added to an order.

### Fixes
- Resolved a problem with the Refund Manager. In rare cases, a promotion had no label, leading to a display error.
- The subscription overview now functions correctly even if a customer was deleted.

## [4.3.0] - 2023-11-08
### Improvements
- Refund Manager can now be opened in combination with the SwagCommercial plugin
- Compiling assets without a database is now possible
- Installing the plugin via Composer shows no warnings
- Timeout for Mollie API requests has been increased to 10 seconds
- Some external mollie links were tagged with a "noopener" and "noreferrer" anchor tag

### Fixes
- Vouchers can now be used with bundle products

## [4.2.0] - 2023-10-04
### Features
- The new payment method POS (Point of Sale) is now available. Together with Mollie's POS terminals, Shopware can now also be used for offline payments in your store. You can find out more about the Mollie POS terminals here: https://www.mollie.com/en/products/pos-payments
- The new payment method TWINT is now available (coming to your Mollie account soon).

### Improvements
- Refunds via Refund Manager now support any number of line items for refunds. The problem with the maximum size of the metadata memory starting from approx. 10 pieces has now been resolved.
- The Refund Manager now also supports a line item refund with a quantity of 0. This makes it possible to refund an allowance for an item without a quantity and to also see this article in the composition of the refund.
- The RefundStarted Flow Builder event now also contains a variable "amount" for the value of the refund.
- Subscriptions in administration are now supported in search

## [4.1.0] - 2023-09-05
### Improvements
- Apple Pay direct includes additional address line
- Removed regenerator-runtime dependency, which breaks the storefront, in some cases

### Fixes
- When payments were cancelled, not all payments were visible after redirect back to shop. (in rare cases)
- Enabling mollie error mode does not lead anymore to an error when a payment is cancelled
- Fixed some compatibility issues in Shopware 6.4.3.1
- Business Events in Administration can be viewed again in Shopware 6.4.3.1
- Fixed broken layout of the payment methods on the checkout page in Shopware 6.5
- Fixed broken layout of the shipping methods on the cart page in Shopware 6.5
- Fixed deletion of precompiled mollie-payments.js, when building the administration

## [4.0.0] - 2023-06-07
### Breaking Changes
The new version 4.0 has been restructured to support both Shopware 6.4 and the new Shopware 6.5 with one single plugin. This means that the Javascript in the Storefront is now loaded from a separate mollie-payments.js file. That behaviour can of course be turned off if you want to build the storefront on your own (please see documentation for more). If you do not see iDEAL dropdown or credit card components, then this could mean that your (custom) theme does accidentally override the Shopware default theme in a wrong way.

### Features
- Full support for Shopware 6.5
- Renamed the “Credit Card” components to “Card” because it also allows debit cards.

### Fixes
- Fixed wrong static quantity of “1” when building shipping items for Mollie. Custom implementation with different quantities will now also be correctly passed on to Mollie.
- Fixed “division-by-zero” error on missing tax rates in the order for rare cases of shop configurations.
- Fixed error in the Refund Manager ACL. Restricted user roles got an error when creating refunds, although the refund was always correctly passed on to Mollie.

## [3.6.0] - 2023-03-16
### Features
- New payment method "Billie" is now available.
- With the new feature "Automatic cancellation" in the plugin configuration, the previously integrated cancellation of Klarna orders can now be optionally deactivated.
- With the new placeholder "customernumber" for the user-defined order number format, the customer number can now also be integrated into the order number.

### Improvements
- [developer] The deprecated field "mollieStatus" has now been removed from the subscription. The "status" field has been used here for some time.

### Fixes
- Orders with refunds can now be deleted again according to the Shopware standard.
- Fixed compatibility issue with NetInventors' "Prices after login..." plugin.
- Fix problems with the automatic route detector for webhooks in headless shops based on Symfony Flex (.ENV parameter problem)
- Removed the logs entry "Product is no longer a subscription product.." which was incorrectly always created.
- Fixed a TWIG template error in combination with One-Click Payments and Shopware 6.3.5.x
- Wrong "associations" were removed when loading orders, which led to ugly log entries.

## [3.5.0] - 2023-02-23
### Hints
- The plugin configuration "final order status" now only has the expected entries of the status list. Please check whether the configuration is still correct after the update.

### Features
- With the integration of One-Click Payments, customers can easily save credit card details for repeating orders. No sensitive data is stored in Shopware.
- The Refund Manager now offers the option of specifying internal comments for refunds in addition to official account statement descriptions.
- New Flow Builder events CheckoutSuccess, CheckoutFailed and CheckoutCanceled for the storefront. This means that events during the payment process can be dealt with individually.

### Improvements
- The column "Mollie" in the order overview of the administration now also shows the Mollie ID of the order.
- New DEBUG log entry if a subscription could not be created correctly due to invalid data.
- The plugin configuration now immediately shows instructions for the individual order number area, and not only when you configure something.
- The plugin configuration for the final order status now only shows the normal status entries of orders.

### Bug fixes
- Fixed the Javascript issue due to Apple Pay Direct in the storefront.
- Fixed the problem where an automatic "cancellation" of Klarna orders via administration did not use the correct API key of the sales channel.
- Fixed the problem where anonymizing the URL in the logs didn't work properly. However, this only applies to tokens that are used once during the payment process.

## [3.4.0] - 2023-01-10
### Breaking changes
- For the future extensions for subscriptions we had to adjust the webhooks for them. If there are firewall rules for this, these rules must be adjusted for the new webhooks: https://github.com/mollie/Shopware6/wiki/Webhooks
- Statuses (badges) for subscriptions are no longer loaded directly from Mollie but are obtained from the local database. This new and empty field is usually filled in automatically. If status entries are unexpectedly empty, please let us know.
- Since we always strive to deliver the best quality, we were forced to stop supporting older Shopware versions below 6.3.5. If this is a problem, please contact us to find a possible solution. We regret this step and ask for your understanding. This is the only way to maintain high quality in the long term.

### Features
- New management for subscriptions. These can now also be paused, renewed or suspended (once).
- Apple Pay Direct is now also available in the off-canvas and in the shopping cart as an express payment method.
- New feature for "rounding adjustments" to be able to make payments with special rounding settings in Shopware.
- New user roles and permission options for subscriptions and the Refund Manager in the administration
- Possibility to configure a custom format for order numbers in Mollie

### Improvements
- Protections for API keys. It is no longer possible to enter a live API key in the test field and vice versa.
- The plugin configuration was rebuilt to give a better overview
- Credit card components now also work with Shopware's CSRF mode
- Improved compatibility with the "Best Practice Checkout" plugin
- Icons of payment methods are now loaded via a different way on initial installation. This is good if "file_get_contents" is not allowed on the server.
- The refund manager now shows specific error texts in the alerts if an error occurs.
- Unintentional spaces in the salutation of an address are now filtered out. This led to problems with orders.
- Add new debug log entries for all changes of payment status and order status (Order State Management)
- Apple Pay log entries are now only created if Apple Pay is also active. These were always created by mistake.
- Apple Pay does not support a company name. Therefore, a company name stored in the account is now also removed when paying with Apple Pay Direct, since the address of Apple Pay should always be used here.

### Fixes
- Fixed broken snippets for Flow Builder triggers since Shopware 6.4.18
- Fixed an incorrect rounding display of "shipping" amount values ​​in the administration
- Fixed the rare issue "Struct::assign() must be type of array" during a checkout

## [3.3.0] - 2022-11-09
### Improvements
- The Refund Manager now also supports promotions related to delivery costs.
- The setting that customers are created at Mollie is now inactive for new installations by default.

### Fixes
- Fixed a crash in combination with other payment provider plugins (Attempted to load class HandleIdentifier and Constant).
- Fixed an issue in the Refund Manager where it was not possible to overwrite the final amount individually again for LineItem-based refunds.
- Fixed a typo in the order state mapping in the plugin settings.

## [3.2.0] - 2022-10-13
### Features
- Remove SEPA Direct Debit. This is not available anymore for regular and initial payment attempts.

### Improvements
- The subscriptions in the storefront do not show a dropdown for the country anymore inside the address-edit form, because the country cannot, and must not be changed.
- Subscription forms in the Storefront do now also support the CSRF mode "Ajax" of Shopware.
- Smaller improvements of our debug logs

### Fixes
- Fix a problem with opening orders in the Administration, that are paid with AMEX credit cards. Because of an issue with the displayed card logo, these orders led to errors in the Administration.
- Fix a problem with a broken link in the Storefront when updating the payment method of a running subscription.
- Add a missing german snippet for an error in the cart with subscription products ("...not all payment methods available..")

## [3.1.0] - 2022-09-29
### Improvements
- The Custom-Fields of an order in Shopware are now also enrichted with Mollie data via webhooks if the customer does not return to the actual finish page.
- The clickable links within the plugin configuration have now also been implemented for Shopware versions <= 6.3.

### Fixes
- Fixed a problem that webhook updates of existing subscription orders might lead to new orders in Shopware.
- Fixed wrong order times in emails (UTC times) when order confirmation emails are triggered by the combination of Flow Builder + webhooks.
- Fixed a rare error "Customer ID is invalid when creating and order".

## [3.0.0] - 2022-09-12
### (Possible) Breaking Changes
The new version 3.0 offers official support for "headless" shops. With the help of "Automatic Route Detection" we have tried to avoid "breaking changes" for new and old payments. Should a problem arise, we have instructions here: https://github.com/mollie/Shopware6/wiki/Headless-setup-in-Shopware-6

### Features
- Support for "Headless" systems
- Out-of-the-box support for the Shopware PWA
- Display of (anonymous) credit card data in an order within the administration (for new orders)
- Subscription feature can now also be disabled if not needed.
- New configuration to skip failed subscription renewal payments, so that only valid payments lead to a new order in Shopware.

### Improvements
- Buttons in the Refund Manager now show a progress if a refund takes a little longer.

### Fixes
- Fixed a NULL problem in OrderLineItemAttributes that could occur in a few shops.

## [2.5.0] - 2022-08-29
### Improvements
- All Mollie Flow Builder events now support the use of email actions.
- Refunds in the Refund Manager can now be created with more positions than before. Due to a limitation on Mollie's side, the data is now internally compressed and thus reduced.

### Fixes
- Mollie subscriptions are now only started with the next interval to avoid an initial double booking

## [2.4.0] - 2022-08-10
### Features
- The Refund Manager can now be deactivated in the plugin settings. This prevents employees from using it when another system is responsible for refunds.

### Improvements
- The selection of iDEAL banks is now mandatory. As a result, the customer can no longer forget it, and the checkout process on the Mollie payment page is reduced by 1 step.
- The Shopware standard behaviour for failed payments is now activated by default when the plugin is installed the first time.

### Fixes
- When creating subscriptions, the mandate of the initial payment was not used explicitly. If the customer already has several mandates, Mollie may have used the wrong one for recurring payments.
- The "additional" field in the address is no longer sent to Mollie if there are only spaces in it. This caused a problem when creating the payment.
- Fixed warnings for "CLI commands" in combination with the PSR Logger, Shopware 6.4.10.1 and PHP 8.0

## [2.3.1] - 2022-08-01
### Fixes
- Fixed the problem with MailTemplate errors when installing/updating the plugin in combination with a shop that only has DE as the default language
- Fixed problems with internal loading of LineItems when CustomFields are empty (NULL)

## [2.3.0] - 2022-07-13
### (Possible) Breaking Changes
This release brings support for the bulk editing of products in the Administration. Due to internal changes, please verify the configured "Voucher Types" of your products after the update. There should be no problem, but please verify that these settings still exist, or set them again.

### Features
- Brand new support for subscriptions in Shopware. Configure products and sell them based on daily, weekly or monthly subscriptions. Read more here: https://github.com/mollie/Shopware6/wiki/Subscription
- Add new Order State Mappings for refunds and partial refunds.
- Add new in3 payment method
- Add SEPA Direct Debit payment method

### Improvements
- Mollie product properties are now available in the bulk editing within the Administration.
- The detail view of an order in the Administration does now show the Paypal or SEPA reference number.
- The Refund Manager does now show the total sum with and without taxes

### Fixes
- Fix edge case problem where an expired order could not be re-paid from within the Storefront.

## [2.2.2] - 2022-05-11
### Features
- With the new setting "Final order status" you can lock a selected status as the final status. From this point on, the status will only be changed in the case of refunds and chargebacks. This feature is particularly helpful in combination with logistics plugins and logistics processes that differ from Mollie.

### Improvements
- The payment status "expired" now leads to an order status of "cancelled" instead of "failed". This is more in line with reality.
- Compatibility support for the "Article number direct URL" plugin.

### Fixes
- The plugin now ignores webhook updates for orders that started with Mollie, but were not ultimately completed with Mollie.
- Fixed an issue with the checkout in combination with mixed tax rates, discount codes and using a net-price customer group

## [2.2.1] - 2022-04-27
### Features
- We've added the brand new "Smart Contact Form" as new support option in plugin configuration. If you use this for support, we will automatically get all data that we need to help you as good as possible.

### Improvements
- Improved and fixed handling of orders with different tax rates and promotions. Due to technical circumstances a promotion in Mollie is also 1 line item. Because of the fact that only 1 tax rate is possible, a mixed tax rate for promotions is now calculated and used inside Mollie to at least ensure that orders like this can be completed.

### Fixes
- Fix problem with Refund Manager and specific API keys for different Sales Channels. It should now always use the correct API key from the Sales Channel of the order.
- Fix problem where the Mollie Limits feature accidentally reduced payment methods in the account and logos in the footer. Please also clear your caches after updating.

## [2.2.0] - 2022-03-23
### Features
- Release of the brand new Refund Manager in Administration and for your API. Handle full refund processes with transactions, stock and Flow Builder events in an intuitive refund form.
- New Apple Pay Display Restrictions in the plugin configuration allow you to hide Apple Pay Direct buttons on different pages without coding.
- New MollieOrderBuilder Event to customize metadata when creating orders (Feature for developers).

### Improvements
- Important change and fix for the Order Transactions in Shopware. When a customer creates additional payment attempts if the first one fails, then the status history in the Administration and API got out of sync. Mollie will now always use the correct latest transaction of Shopware and append the new status to this transaction and keep it consistent.
- SEPA transaction will now stick with "In Progress" if started and will not transition back to "Open".
- Payments with status "Created" will now be detected as "failed". This should not happen by Mollie, but did somehow.
- Credit Card payments with status "Open" will now be detected as "failed". This should also not happen.
- Apple Pay Direct will now use the Shopware customer number range when creating new guest accounts.
- Replaced Javascript code in Apple Pay Direct to remove error outputs in Internet Explorer.
- Removed jQuery to prepare for Shopware 6.5.
- Improved performance of the "voucher availability" detection when working with a large set of products.
- ING'Homepay will now always be disabled when updating payment methods. This payment method is deprecated in Mollie since a while.
- Removed automatic activation of Mollie payment methods when installing the plugin. This led to a problem due to ING'Homepay, and one would not expect this to happen in that place.

### Fixes
- Fix problem that some fields such as images and names of payment methods got removed and set back to factory defaults after updating them.
- Fix problem with payment methods that were imported twice on an update. (This does not fix previously duplicated methods though).
- Fix a problem with the IPAnonymizer in combination with IPv6 inside the Logging module.
- Fix broken "update payment method" button in the plugin configuration in older Shopware versions.
- Fix rare problem where "checkSystemConfigChange()" in the Mollie plugin threw an exception.
- Fix a problem where shipping wasn't possible due to missing data in the Administration (Cannot read a.shippedQuantity)
- Fix very rare problem of an exception when using the "Mollie Limits" feature.
- Fix Javascript error that is visible if iDEAL is assigned to a Sales Channel but Mollie isn't even configured correctly.

## [2.1.2] - 2022-03-16
This version brings support for the latest Shopware 6.4.9.0. The previous version was never meant to be available for this Shopware version. The Shopware store did unfortunately automatically approve the new Shopware version, which has been turned off for the future. We are sorry for any inconvenience.

## [2.1.1] - 2022-02-16
### Improvements
- Due to a bug (NEXT-20128) in the current Shopware version 6.4.8.x there is a Javasript error "HTML element not found" on every page in the shop. We want the best for you, so we've figured out a way to get rid of this error.

## [2.1.0] - 2022-02-15
### Features
- The new configuration "Mollie Payment Limits" helps you to automatically hide payment methods that are not available by Mollie for the current cart total amount. You can of course use your Shopware availability rules from the Rule Builder and also use the optional Mollie Limits on top of it.
- We present the new plugin configuration. Improved onboarding, improved structure and more descriptions help you with an even easier configuration of the plugin.

### Improvements
- Apple Pay Direct payments do now also get additional information like the Mollie-ID, etc. in the "Custom Field" of the Shopware order.
- Avoiding of Javascript errors in the shipment section of an order in the Administration. This is just an additional optimization.

### Fixes
- SEPA bank transfer payments with status "open" do now lead to a successful order.
- PayPal payments with status "pending" do now lead to a successful order.

## [2.0.0] - 2022-01-31
Welcome to MolliePayments v2.0!

We hope you enjoy the wide variety of new features, updates and fixes.

Great to have you as our customer :)

### BREAKING CHANGES
Because of Flow Builder we had to remove the fixed shipments/refunds that happened on status transitions.

But no worries, there is a new "Automatic Shipping" feature that is enabled by default and makes sure

that it works in the same way after updating to v2.0.0

### Features
- Brand new Flow Builder Integration. Listen for incoming Mollie Webhooks, or automatically trigger shipments and refunds. The flexibility of Flow Builder is now coming to payments!
- New "Automatic Shipping" feature that can be turned off when you want to ship with other features such as Flow Builder.
- Shipment Tracking is now available in the Administration. If you have tracking data, then it should automatically be prepared when you start a shipment.
- Partial Shipments for line items is back and now possible in the Administration.
- New Mollie Action buttons for shipment and refunds in Administration to improve the way you work with Mollie orders.
- "Chargeback" payment status is now supported in the plugin.
- Refunds can now be done through the Shopware API.
- New Logging system. Our improved and better debug logs are now only in the file system next to other Shopware logs.
- New button to update payment methods without re-activating the plugin is now existing in the plugin configuration.
- New CLI command to update the Apple Pay Domain Verification file in case it changes.
- Mollie information like Order ID, Transaction ID, PayPal Reference and SEPA Reference is now saved in the custom fields of the order.
- Apple Pay Direct is now also supported on custom product pages built with the CMS system.

### Improvements
- Add Twig blocks to Apple Pay Direct buttons.
- Improve margins of Apple Pay Direct buttons.
- The lastUpdated of the order itself will now be updated too on incoming payment status changes.
- The plugin will now automatically install new payment methods on plugin updates in the future.

### Fixes
- Fix Credit Card Components in Internet Explorer
- Fix Credit Card Components on the Edit Order page after a failed payment.
- Fix compatibility problem with the official Klarna plugin.
- Fix problem with checkouts in Shopware 6.3.3.1
- Fix wrong router in PaymentController that could lead to wrong URLs with other plugins.
- Fix problem of duplicated payment methods after renaming them and updating the plugin.

## [1.5.8] - 2021-12-14
### Improvements
- Add compatibility for EasyCoupon Plugin by Net Inventors GmbH

## [1.5.7] - 2021-11-15
### Features
- New payment method "Klarna Pay Now" is available

### Improvements
- Apple Pay Direct forms will now not be rendered in the storefront anymore if Apple Pay Direct is not enabled

## [1.5.6] - 2021-11-08
### Improvements
- Failed payments now lead to a "failed" payment status instead of "cancelled"
- Add compatibility for plugin "ACRIS Checkout Shipping Payment Preselection"
- Add compatibility for plugin "Custom Products"

### Fixes
- Fix rare rounding problem with "net price" based shops and vertical tax calculation
- Fix Javascript problem in administration that prevented line items from being displayed in the order view

## [1.5.5] - 2021-10-27
### Features
- Add new payment method "Voucher". Configure your products as Eco-, Meal- or Gift Voucher and let your customers use supported voucher systems.
- New Shopware API route to ship orders. Use these routes for easy integrations of logistic systems and other tools.

### Improvements
- Increase timeout for the communication with Mollie to allow stable payments also during peak times.
- API keys are now shown as password fields in the administration
- Improvement of plugin compatibilities with the new usage of the RouterInterface instead of the original Router in Shopware.

### Fixes
- Removed unsupported EMV Apple Pay cards from Apple Pay Direct
- Customization of the checkout page might lead to Javascript problems with the credit card components. This has been improved now.

## [1.5.4] - 2021-09-15
### Features
- The feature "Create Customers in Mollie" is back. It's now ready for Multi-Sales-Channel setups and both, test and live modes. If enabled customers will be created in Mollie and linked to their orders and transactions.

### Improvements
- Apple Pay Direct has been completely refactored and improved for better stability, features and performance.
- Apple Pay Direct does now reuse the existing Shopware customer, if already signed in.
- Apple Pay Direct does now also work for earlier Shopware Versions 6.1.x, ...

### Fixes
- Fix problem where the redirect to the Mollie Payment page after a failed payment led to a NOT FOUND error in Shopware 6.4.3.1
- Fix problem with lost sessions, carts and disappearing promotions in combination with different Sales Channels due to wrong Apple Pay ID checks in the background.
- Fix problem where wrong arguments led to an exception while trying to log data.
- Optional MOLLIE_SHOP_DOMAIN variable for custom Webhook URLs is now correctly used again.
- Fix problem of "PROMOTION_LINE_ITEM Not Found" in earlier Shopware versions 6.1.x
- Fix general checkout problems in Shopware Versions 6.1.x

## [1.5.3] - 2021-08-11
### Bugfix
- fix registration error with apple pay direct
- fix plugin config default values, if plugin is newly installed. In administration of the plugin config testModus has been shown as off, though it was on.

## [1.5.2] - 2021-08-05
Refactoring and code improvements when changing payment transitions

### Bugfix
- fixed a bug when entering wrong creditcard informations. The payment process is not blocked any more
- fixed a bug that prevented to pay if a promotion has been in cart
- Change router in MollieOrderBuilder to Shopware router instead of symfony router

## [1.5.1] - 2021-07-21
### Bugfix
Fixed delivery cost transmission to mollie.

## [1.5.0] - 2021-07-21
### Feature
Added full support of Partial Refunds (just add wished sum and create refund directly at mollie)

### Refactoring
- refactored payment handler for more code stability
- Add new transition service for payments
- Reuse mollie orders - don't create new mollie orders in case of cancelled or failed payments
- Reuse mollie order payments if possible (otherwise create new payment) if former payment has failed or has been cancelled

### Bugfix
Fixed shipping transition

## [1.4.3] - 2021-07-07
### Bugfix
fix backwards compatibility

## [1.4.2] - 2021-07-06
### Bugfixes
- Fixed Apple Pay domain verification, if Apple Pay direct should be used
- Refactored web hook notifications. Some mollie order states haven't been recognized correctly, which results that payment status didn't change.
- Apple Pay isn't shown as payment method in storefront if browser isn't supporting Apple Pay or no wallet has been configured on used device
- Added a fix for Ideal issuer dropdown in Shopware 6.4 templates
- Fixed a bug that prevented order state transitions in multilingual shops
- Fixed a bug for shopware versions > 6.4 with the return url. The former used return url could be too long for mollie api. Now we use only short urls

### Features
- Added a link to the developer section of the mollie dashboard in administration config
- Added the mollie payment url to orders in Shopware backend
- If you activate test mode in administration, every mollie payment in storefront gets an "test mode" addition in the payment method name

## [1.4.1] - 2021-05-17
### Bugfix
- Transaction status is set to paid if mollie sets klarna order payment status from authorized to completed

## [1.4.0] - 2021-05-06
### Features
- Plugin is now Shopware 6.4 compatible
- "Create customer at mollie" feature deactivated and removed from administration

### Bugfix
- Fixed credit card components return url (thanks to fjbender for finding and solving this bug)

### Notes
- If you use the new currency rounding feature (total sum), we calculate the surcharge or discount just like Shopware with 0% taxes.

## [1.3.16] - 2021-04-22
### Bugfix
- Fixed bug that prevented to edit an order in the administration

## [1.3.15] - 2021-04-21
### Bugfixes
- Added webhook url to payment node to transmitted data when order is placed. In edge cases this could lead to permanent payment status „processing“ in Shopware
- Fixed a bug when payment has been set to refunded in administration. The bug has read wrong plugin configuration in multi sales channel environments
- Updated custom parameters for bank transfer payment method.
- Added better order state handling if order status change is configured in plugin
- Fixed wrong handling of feature „Do not create customers at Mollie“
- Fixed wrong error handling during checkout in case of mollie api errors

## [1.3.14] - 2021-03-15
- Fixed bugs and refactored shipment submission to mollie api

## [1.3.13] - 2021-02-24
- Fixed js error in creditcard-components that could break payment process
- Added fallback for empty order expires at configuration (let mollie handle this)
- Fixed browser back button behaviour on mollie payment page when a payment has failed before

## [1.3.12] - 2021-02-15
### Improvements
- MOL-137: Updated translations for german and dutch

### Bugfixing
- MOL-142: Due to different tax calculations between Mollie API and Shopware, there could be different calculated tax amounts in some edge cases. This leads to an error in Mollie Api when orders are submitted for payment. Customer weren't able to pay successfully.
- MOL-140: If "Use standard failed payment redirect." in mollie configuration has been activated, following error could occur. If payments on the Mollie pages were unsuccessful, a customer was sent to the "order successful" page instead of the error page. The problem has been solved

## [1.3.11] - 2021-01-25
### Fixes
- Fixes an issue where cancelled Klarna orders could not be retried when using the Mollie redirect page.
- Fixes an issue where an incorrect router object was being used.

## [1.3.10] - 2021-01-14
### Fixes
- Fixes an error thrown when cancelling an order through the administration in Shopware 6.2.x and older.
- Fixes an issue where the incorrect API key was being used.

## [1.3.9] - 2020-12-28
### Fixes
- Resolves an issue where orders would not be getting the configured state when authorizing payment with Klarna, after a previous payment attempt for this order had been cancelled, using the Mollie redirect feature.
- Klarna orders that are cancelled through the Shopware administration will now also get cancelled in Mollie dashboard.
- Fixes the "Standard failed payment redirect" from not using the correct Shopware routing.
- New installations will now load svg payment icons. Existing installations will keep using the png variants, until they are deleted from the media library and the plugin is reactivated.

## [1.3.8] - 2020-12-16
### Fixes
- Fixed encoded urls no longer being accepted by the API. Updated API client to 2.27.1

## [1.3.7] - 2020-12-07
### Features
- Added an option to disable Apple Pay Direct when Apple Pay is available as payment method

### Bugfix
- Improved compatibility with Shopware's Paypal integration
- Fixed an issue where the wrong payment method was shown in the administration, when a different one was selected in Mollie
- Fixed several minor bugs

## [1.3.6] - 2020-11-13
- Fixed multiple issues with completing Apple Pay Direct orders
- Fixed an incompatibility issue with Custom Products
- Fix for issue with VAT-related price rounding
- Fix for an illegal return type

## [1.3.4] - 2020-10-30
- Mollie locale fix for creditcards components.
- Order state automation on failed payments fix.
- General error handling for issue creating documents in the backend.

## [1.3.2] - 2020-10-26
- Bugfix with order states not working on failed payments.

## [1.3.1] - 2020-10-21
- Bugfix: Small error fixed with customer registration and headless storefronts.

## [1.3.0] - 2020-10-19
### Features
- Added order state automation for Authorized orders. (Klarna)
- Added the option to toggle single click payments on and off.

### Bugfixes
- Fixed redirection error on certain sales channels after failed payments.
- Fixed VAT calculation errors on certain customer groups.

## [1.2.3] - 2020-10-09
- Added "Authorized" status in order overview for Klarna.
- Added Single Click Payments for second time credit card payment users.

## [1.2.2] - 2020-09-28
- Fixed a bug with not updating the transaction status from Klarna orders to paid.

## [1.2.1] - 2020-09-17
- Added Toggle option to choose standard Shopware or Mollie failed payment redirects.

## [1.2.01] - 2020-09-07
- Apple Pay Direct wurde auf der Produkt- und Listenseite hinzugefügt.

## [1.0.19] - 2020-08-04
### Features
- Added the Mollie Order ID to the order detail in the administration
- Added the preferred iDeal issuer to the customer detail in the administration
- Added order state automation

### Bugfixes
- Fixed a bug with product URLs in the request to Mollie
- Fixed issues with the payment state staying in progress after failed payments

## [1.0.18] - 2020-07-29
### Bugfix
- Fixed an issue where custom products would cause an error.

## [1.0.17] - 2020-07-23
### Features
- An event is triggered when the payment failed or passed, other plugins can act on this event
- SKU number of line items is available in the Mollie Dashboard

### Bugfixes
- Multiple VAT rates in the basket no longer cause an exception
- API test-button only appears within Mollie's configuration

## [1.0.16] - 2020-07-08
### Features
- Test-button in the plugin configuration to validate the API keys.

### Bugfixes
- Mollie Components is now a javascript plugin and works in live mode.

## [1.0.15] - 2020-06-25
### Bugfixes
- Fixed an issue where changing the delivery status would cause an exception.
- Fixed an issue where Apple Pay would show up on not-supported devices.

## [1.0.14] - 2020-06-15
### Bugfixes
- Fixed an issue where the vat amount on orders for net free customers was off.
- Fixed an issue where Mollie Components wasn't compatible with Shopware 6.1.5.

### Features
- The debug mode now indicates whether a payment is in live or test mode.

## [1.0.13] - 2020-05-28
### Hotfix
- Reversed a fix for customers with display of net prices, as it had the unwanted side effect of customers paying the net price.

## [1.0.12] - 2020-05-28
### Features
- iDeal issuer selection in the checkout
- German translations for Mollie Components
- Mollie Components now works as a Storefront plugin.

### Bugfixes
- API exception with vat free or net orders
- Possible exceptions during checkout or payment retry
- Possible exceptions when transitioning payment states

## [1.0.11] - 2020-05-20
### Features
- The Mollie PHP SDK is updated
- Payment methods are now installed with icons
- Orders can be partially shipped and/or refunded
- Order payment state will always first be set to in progress when payment at Mollie starts
- Failed payments can now be retried

### Bugfixes
- Fixed an issue where changing the delivery status of an order would cause an exception
- Fixed an issue where order lifetime had the wrong timezone
- Fixed an issue where tax was calculated on orders from tax free countries
- Fixed an issue where credit card components wasn't available on translated payment methods
- Fixed an issue where credit card components couldn't be loaded in a shop with in a subdirectory
- Fixed an issue where the webhook URL wasn't sent to Mollie

## [1.0.10] - 2020-05-05
- Created a bugfix for backwards compatibility of payment states

## [1.0.9] - 2020-05-04
- Fixed payment state transitions in the latest version of Shopware 6 (backwards compatible)
- Added a debug mode, to gather extra information in the Shopware 6 log in the administration

## [1.0.8] - 2020-05-04
- Webhook URLs are correctly set in production environments
- Configuration has All Sales Channels as fallback data
- Vat amount can be 0.0 (e.g. when an order is tax free)

## [1.0.7] - 2020-04-06
- Fixed issues with multi channel API keys

## [1.0.4] - 2020-03-30
- Added Mollie Components
- Fixed an issue where API keys couldn't be different for each sales channel

## [1.0.3] - 2020-01-13
- Created fix voor version 6.1+ of Shopware

## [1.0.2] - 2019-11-06
- Fixed activation of the plugin
- Payment methods are now activated in your Mollie dashboard also when they're being activated in the shop

## [1.0.1] - 2019-10-14
- Fixed an issue where the monolog logger service wasn't available during the activate lifecycle.
