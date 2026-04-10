# STORE-1898: PayPal Express intermittent failures – root cause analysis

## Summary

PayPal Express (PPE) was failing intermittently for customers with a generic checkout error. Investigation traced the issue to two bugs in the plugin code.

---

## Bug 1 (Primary): `SESSION_BASE_TIMEOUT` was 2 ms, not 2 seconds + sleep happened before first check

### Location
`src/Components/PaypalExpress/PayPalExpress.php` – `loadSession()`

### What happened
The `loadSession()` method polls the Mollie session API up to 5 times waiting for the `shippingAddress` field to appear. This is needed because Mollie can take *several seconds* to receive address data back from PayPal after the buyer confirms.

Two issues existed in the retry loop:

**1. Wrong unit for `SESSION_BASE_TIMEOUT`** – `usleep()` takes **microseconds**, but the constant was set as if it were milliseconds:

```php
private const SESSION_BASE_TIMEOUT = 2000;  // intended as ms, but usleep takes µs!
// ...
usleep($sleepTimer);  // → sleeps for 2 000 µs = 2 ms per step
```

With 5 retries the **maximum total wait was only ~30 ms** (2 + 4 + 6 + 8 + 10 ms), while Mollie often needs 1–3 seconds. The retries were therefore functionally useless.

**2. Sleep happened before the first API call** – the original loop called `usleep()` at the top, meaning every attempt (including the very first one) waited before querying the API. This wasted time on the happy path where Mollie already had the address.

```php
// original order (wrong):
for ($i = 0; $i < self::SESSION_MAX_RETRY; ++$i) {
    $sleepTimer = self::SESSION_BASE_TIMEOUT * ($i + 1);
    usleep($sleepTimer);   // ← waits BEFORE the first request too
    $session = $mollie->sessions->get($sessionId);
    if (...shippingAddress...) { break; }
}
```

`FinishCheckoutRoute` then checked `$methodDetails->shippingAddress` and found it `null`, throwing `PaypalExpressException::shippingAddressMissing()`. The storefront controller caught this and redirected the buyer back to the cart with a generic danger flash message — the "sometimes failing" symptom.

### Fix
1. Changed `SESSION_BASE_TIMEOUT` from `2000` to `500_000` (0.5 seconds in microseconds). Maximum total wait is now **7.5 seconds** (0.5 + 1 + 1.5 + 2 + 2.5 s).
2. Moved `usleep()` to *after* the API call and address check — the first attempt is now immediate; sleep only happens between retries.

```php
private const SESSION_BASE_TIMEOUT = 500_000;  // 0.5 s per step, 7.5 s max total

// corrected order:
for ($i = 0; $i < self::SESSION_MAX_RETRY; ++$i) {
    $session = $mollie->sessions->get($sessionId);
    if (...shippingAddress...) { break; }
    // Sleep between retries only — not before the first attempt
    $sleepTimer = self::SESSION_BASE_TIMEOUT * ($i + 1);
    usleep($sleepTimer);
}
```

---

## Bug 2 (Secondary): Empty `givenName` when PayPal returns a single-word name

### Location
`src/Struct/Address/AddressStruct.php` – `createFromApiResponse()`

### What happened
Some PayPal accounts have a single-word display name (e.g., `"Smith"`). In that case Mollie's API returns a `familyName` without a `givenName`. The code tries to split `familyName` into first + last:

```php
if (property_exists($address, 'familyName') && !property_exists($address, 'givenName')) {
    $nameParts = explode(' ', $address->familyName);
    $address->familyName = array_pop($nameParts);
    $address->givenName  = implode(' ', $nameParts);  // '' when only one word
}
```

With `familyName = "Smith"`, `$nameParts` is `["Smith"]` after `array_pop`, so `implode` returns `""`. Shopware's `RegisterRoute` requires a non-empty `firstName`, so `createGuestAccount()` would throw a `ConstraintViolationException`, log the error, and return `null`. `prepareCustomer()` then threw `'Error when creating customer!'` → same generic error redirect.

### Fix
Added a fallback: when the split produces an empty `givenName`, reuse `familyName` as `givenName`:

```php
if ($address->givenName === '') {
    $address->givenName = $address->familyName;
}
```

This ensures both `firstName` and `lastName` are non-empty, passing Shopware validation. The resulting name (`Smith / Smith`) is a reasonable fallback for a single-word PayPal account name.

---

## Bug 3: Two copy-paste bugs in `CustomerService` caused wrong address assignment

### Location
`src/Service/CustomerService.php` – `updateCustomer()` / address persistence

### What happened
Two separate copy-paste mistakes meant that the **billing address was always used for shipping** and **billing address data was built from shipping address data**.

**Bug 3a – `defaultShippingAddressId` pointed to billing ID**

```php
// wrong:
$customer = [
    'defaultBillingAddressId'  => $defaultBillingAddressId,
    'defaultShippingAddressId' => $defaultBillingAddressId,  // ← copy-paste error
];
```

`$defaultBillingAddressId` was assigned to both keys. Shopware therefore set the same address (billing) as the default shipping address for the customer, silently ignoring the actual shipping address.

**Bug 3b – billing `StructuredData` was set from shipping data**

```php
// wrong:
$data->set('billingAddress', $shippingAddressData);  // ← wrong variable
```

Even when billing address data was fully constructed into `$billingAddressData`, the `set()` call referenced `$shippingAddressData` instead. The billing address stored in Shopware was therefore identical to shipping.

### Fix
Variable names corrected:

```php
// Bug 3a fix:
'defaultShippingAddressId' => $defaultShippingAddressId,

// Bug 3b fix:
$data->set('billingAddress', $billingAddressData);
```

### Impact
Any PayPal Express checkout where the buyer had distinct billing and shipping addresses would silently save both as the shipping address. Tax calculations and invoicing could be affected.

---

## Flow overview (for reference)

```
[Browser] PayPal Express button clicked
    → POST /mollie/paypal-express/start   (StartCheckoutRoute)
        - creates Mollie session
        - persists session ID in cart extension
        - redirects browser to PayPal redirect URL

[Browser] User completes PayPal
    → GET /mollie/paypal-express/finish   (FinishCheckoutRoute)  ← bug 1 triggers here
        - loadSession() polls Mollie for shipping/billing address
        - if address missing → exception → cart page with error
        - if address present → create/find guest customer        ← bug 2 triggers here
        - persist auth ID in cart
        - redirect to /checkout/confirm

[Browser] /checkout/confirm
    - PayPalExpressPaymentRemover checks isPayPalExpressComplete()
    - shows only PPE as payment option
    - buyer places order using authenticationId from cart extension
```

---

## Tests added
- `tests/PHPUnit/Struct/Address/AddressStructTest.php` – covers Bug 2: single-word name fallback, full-name split, preserving an existing `givenName`, and the general `createFromApiResponse` paths.
- `tests/PHPUnit/Components/PaypalExpress/PayPalExpressLoadSessionTest.php` – covers Bug 1: immediate return when address is available on the first API call (no retry), retry until address appears, and verifying only one API call is made on the happy path. A namespace-level `usleep()` override prevents real sleeping in CI.
- `tests/PHPUnit/Service/CustomerServiceAddressTest.php` – covers Bug 3a (`reuseOrCreateAddresses` maps shipping/billing entity IDs to the correct Shopware keys) and Bug 3b (`createGuestAccount` populates the billing address data bag from billing data, not a copy of shipping data).

---

## What to keep in mind for future work
- `usleep()` takes **microseconds**; `sleep()` takes seconds. Mixing these units is a silent bug.
- Always sleep *between* retries, not before the first attempt — the first attempt is often fast and sleeping upfront wastes time on the happy path.
- `SESSION_BASE_TIMEOUT` drives `loadSession()` which is also called in `StartCheckoutRoute` (for session reload, not address data). A future optimisation could skip the address-polling loop in that case.
- The `FinishCheckoutRoute` also checks for `billingAddress`. Mollie can return a billing address without `streetAndNumber`; in that case it is silently ignored and shipping is used for billing. This is acceptable but worth knowing.
- Copy-paste variable name errors in `CustomerService` are hard to spot because PHP does not warn about undefined variables in array literals — always grep for symmetric variable pairs like `$defaultBillingAddressId` / `$defaultShippingAddressId` when reviewing address-related code.

