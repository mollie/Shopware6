# Payment Methods

This document describes how payment methods are structured, registered, and implemented in the Mollie Shopware 6 plugin.

---

## Overview

Each Mollie payment method is represented by a PHP handler class in `/src/Handler/Method/`.
There are currently **31 active** and **~8 deprecated** payment method handlers.

---

## File Structure per Payment Method

A payment method requires the following pieces:

| Component | Location | Required |
|---|---|---|
| Handler class | `/src/Handler/Method/[MethodName]Payment.php` | Yes |
| Service definition | `/src/Resources/config/services/handlers.xml` | Yes |
| Registration in installer | `/src/Service/PaymentMethodService.php` → `getPaymentHandlers()` | Yes |
| Migration (if name/icon changes) | `/src/Migration/Migration[timestamp][Description].php` | Only if needed |

---

## Handler Class

Every payment method extends `PaymentHandler` and defines three constants:

```php
<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ExamplePayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::EXAMPLE; // Mollie API identifier
    public const PAYMENT_METHOD_DESCRIPTION = 'Example Pay';  // Display name in Shopware
    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;

    public function processPaymentMethodSpecificParameters(
        array $orderData,
        OrderEntity $orderEntity,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer
    ): array {
        // Add method-specific parameters to the Mollie order payload here.
        // Return $orderData unchanged if no customization is needed.
        return $orderData;
    }
}
```

### Key points

- `PAYMENT_METHOD_NAME` is the Mollie API identifier (e.g. `"vipps"`). Use the matching constant from `Mollie\Api\Types\PaymentMethod` if it exists in the vendored SDK (`vendor_manual/`). **If no constant exists, use a plain string instead** – do not add it manually to the vendor file.
- `PAYMENT_METHOD_DESCRIPTION` becomes the default display name in Shopware.
- `processPaymentMethodSpecificParameters()` is the extension point for method-specific Mollie API parameters (e.g. card token, due date, issuer).

### PAYMENT_METHOD_NAME: constant vs. string

**Constant exists** (standard case):

```php
use Mollie\Api\Types\PaymentMethod;

public const PAYMENT_METHOD_NAME = PaymentMethod::IDEAL; // "ideal"
```

**No constant in the SDK** (e.g. a new payment method not yet in the vendored SDK):

```php
// No PaymentMethod import needed
public const PAYMENT_METHOD_NAME = 'vipps';
```

> **Important:** Do not modify `vendor_manual/mollie-api-php/` manually. It reflects the external Mollie PHP SDK and will be overwritten on SDK updates. Missing constants will be added automatically with the next SDK update.

---

## Class Hierarchy

```
PaymentHandler  (src/Handler/PaymentHandler.php)
└── uses PaymentHandlerTrait         (shopware/Component/Payment/PaymentHandlerTrait.php)
    └── delegates to PayAction       (shopware/Component/Payment/PayAction.php)
    └── delegates to FinalizeAction  (shopware/Component/Payment/FinalizeAction.php)

Handler/Method/[MethodName]Payment extends PaymentHandler
```

For older Shopware versions, `PaymentHandlerLegacyTrait` is used instead of `PaymentHandlerTrait`. The base class selects the correct trait automatically.

---

## Service Registration

Every handler must be declared as a service in `/src/Resources/config/services/handlers.xml`:

```xml
<service id="Kiener\MolliePayments\Handler\Method\ExamplePayment">
    <argument type="service" id="Mollie\Shopware\Component\Payment\PayAction"/>
    <argument type="service" id="Mollie\Shopware\Component\Payment\FinalizeAction"/>
    <!-- Add extra <argument> entries if the constructor has additional dependencies -->
    <tag name="shopware.payment.method.async"/>
    <tag name="shopware.payment.method"/>
</service>
```

Both tags are required so Shopware recognizes the class as an asynchronous payment handler.

---

## Registration in PaymentMethodService

After adding the XML entry, add the handler class to `getPaymentHandlers()` in `/src/Service/PaymentMethodService.php`:

```php
// Active methods
$handlers[] = ExamplePayment::class;

// Deprecated methods (to disable during install/update)
$deprecatedHandlers[] = OldPayment::class;
```

This list drives the installation: `installAndActivatePaymentMethods()` iterates over it to create or update entries in the Shopware `payment_method` database table.

---

## Installation Flow

When the plugin is installed or updated, the following happens:

```
PluginInstaller::install() / update()
  └── PaymentMethodService::installAndActivatePaymentMethods()
        ├── getPaymentHandlers()           → Returns all handler class names
        ├── addPaymentMethods()            → Creates/updates payment_method table rows
        │     Fields: handler_identifier, technical_name, name, media_id, active, plugin_id
        ├── activatePaymentMethods()       → Sets active = true for new methods
        └── disablePaymentMethod()         → Sets active = false for deprecated methods
```

`technical_name` follows the pattern: `payment_mollie_[mollie_method_id]`
e.g. `payment_mollie_creditcard`, `payment_mollie_ideal`

Icons are downloaded from the Mollie CDN during installation and stored in the Shopware media library:
`https://www.mollie.com/external/icons/payment-methods/[method_name].svg`

---

## Payment Flow (Runtime)

```
1. Customer selects payment method at checkout
2. Shopware calls PaymentHandler::pay()
      └── PayAction::pay()
            ├── Calls processPaymentMethodSpecificParameters() on the handler
            ├── Calls PayFacade::startMolliePayment()
            ├── Sets transaction status to "IN PROGRESS"
            └── Redirects customer to Mollie payment page

3. Customer completes payment on Mollie

4. Mollie sends webhook → Shopware calls PaymentHandler::finalize()
      └── FinalizeAction::finalize()
            ├── Calls PayFacade::finalize()
            ├── Updates order/transaction status
            └── Throws exception if payment failed
```

---

## Examples

### Simple method (no special parameters)

`/src/Handler/Method/iDealPayment.php`

```php
class iDealPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::IDEAL;
    public const PAYMENT_METHOD_DESCRIPTION = 'iDEAL | Wero';
    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;

    public function processPaymentMethodSpecificParameters(
        array $orderData,
        OrderEntity $orderEntity,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer
    ): array {
        return $orderData;
    }
}
```

### Method with plugin settings (BankTransfer)

`/src/Handler/Method/BankTransferPayment.php`

```php
class BankTransferPayment extends PaymentHandler implements BankTransfer
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::BANKTRANSFER;
    private SettingsService $settingsService;

    public function __construct(PayAction $payAction, FinalizeAction $finalizeAction, SettingsService $settingsService)
    {
        parent::__construct($payAction, $finalizeAction);
        $this->settingsService = $settingsService;
    }

    public function processPaymentMethodSpecificParameters(
        array $orderData,
        OrderEntity $orderEntity,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer
    ): array {
        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());
        $dueDateDays = (int) $settings->getPaymentMethodBankTransferDueDateDays();

        if ($dueDateDays > 0) {
            $orderData['expiresAt'] = $settings->getPaymentMethodBankTransferDueDate();
        }

        return $orderData;
    }
}
```

### Method with custom constructor (CreditCard)

`/src/Handler/Method/CreditCardPayment.php`

Uses `CustomerService` to read a stored card token from customer custom fields and passes it to the Mollie API as `payment.cardToken`.

---

## Migrations

When the display name or icon of a payment method changes, a migration is needed.

Example: `/src/Migration/Migration1770194798iDealWero.php`

```php
// Delete old media file
DELETE FROM media WHERE file_name = 'ideal-icon';

// Update display name
UPDATE payment_method_translation SET name = 'iDEAL | Wero' WHERE name = 'iDEAL';
```

Migration file naming: `Migration[unix_timestamp][ShortDescription].php`

---

## Adding a New Payment Method – Checklist

1. **Create handler class** at `/src/Handler/Method/[MethodName]Payment.php`
   - Extend `PaymentHandler`
   - Define `PAYMENT_METHOD_NAME`, `PAYMENT_METHOD_DESCRIPTION`, `$paymentMethod`
   - Only override `processPaymentMethodSpecificParameters()` if the method needs custom Mollie API parameters (e.g. card token, due date). The default implementation in `PaymentHandlerTrait` returns `$orderData` unchanged.

2. **Register service** in `/src/Resources/config/services/handlers.xml`
   - Add `<service>` block with both tags

3. **Add to installer list** in `PaymentMethodService::getPaymentHandlers()`

4. **Add tests**
   - Create `tests/PHPUnit/Service/MollieApi/Builder/Payments/[MethodName]OrderBuilderTest.php` – copy the pattern from `SatispayOrderBuilderTest.php`; use the correct currency for the payment method
   - Add the handler class to the expected list in `tests/PHPUnit/Service/PaymentMethodServiceTest.php`
   - Add an entry to `$paymentMethodMapping` in `tests/Integration/Data/PaymentMethodTestBehaviour.php` so Behat integration tests can resolve the method by name
   - Create `tests/Cypress/cypress/e2e/storefront/payment-methods/[methodname].cy.js` – copy the pattern from `satispay.cy.js`; verify the payment method appears in checkout (full payment flow is usually not testable automatically)
   - Add an entry to the `payments` array in `tests/Cypress/cypress/e2e/storefront/checkout/checkout-success.cy.js` with `key` matching the Mollie method name and `sanity: false`; leave `caseId` empty if no test management ID is assigned yet
   - Add a row to the `Examples` table in `tests/Behat/Features/payment.feature` with the correct `billingCountry` and `currency` required by the payment method

5. **Update CHANGELOG** in both `CHANGELOG_en-GB.md` and `CHANGELOG_de-DE.md` under the `# unreleased` section

6. **Create a migration** only if an existing payment method entry needs to be updated (e.g. name or icon change)

---

## Key Files Reference

| Purpose | File |
|---|---|
| Base handler class | `src/Handler/PaymentHandler.php` |
| Payment trait (new Shopware) | `shopware/Component/Payment/PaymentHandlerTrait.php` |
| Payment trait (legacy Shopware) | `shopware/Component/Payment/PaymentHandlerLegacyTrait.php` |
| Payment initiation | `shopware/Component/Payment/PayAction.php` |
| Payment finalization | `shopware/Component/Payment/FinalizeAction.php` |
| Service definitions | `src/Resources/config/services/handlers.xml` |
| Installer / registration | `src/Service/PaymentMethodService.php` |
| Plugin installer | `src/Components/Installer/PluginInstaller.php` |
| Payment method handlers | `src/Handler/Method/` (31+ files) |