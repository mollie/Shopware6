# STORE-1898: PayPal Express intermittent failures – root cause analysis

## Summary

PayPal Express (PPE) was failing intermittently for customers with a generic checkout error. Investigation traced the issue to two bugs in the plugin code.

---

## Bug 1 (Primary): `SESSION_BASE_TIMEOUT` was 2 ms, not 2 seconds

### Location
`src/Components/PaypalExpress/PayPalExpress.php` – `loadSession()`

### What happened
The `loadSession()` method polls the Mollie session API up to 5 times waiting for the `shippingAddress` field to appear. This is needed because Mollie can take *several seconds* to receive address data back from PayPal after the buyer confirms.

The retry sleep used `usleep()`, which takes **microseconds**:

```php
private const SESSION_BASE_TIMEOUT = 2000;  // intended as ms, but usleep takes µs!
// ...
usleep($sleepTimer);  // → sleeps for 2 000 µs = 2 ms per step
```

With 5 retries the **maximum total wait was only ~30 ms** (2 + 4 + 6 + 8 + 10 ms), while Mollie often needs 1–3 seconds. The retries were therefore functionally useless and the method returned without address data on every slow response from Mollie.

`FinishCheckoutRoute` then checked `$methodDetails->shippingAddress` and found it `null`, throwing `PaypalExpressException::shippingAddressMissing()`. The storefront controller caught this and redirected the buyer back to the cart with a generic danger flash message — the "sometimes failing" symptom.

### Fix
Changed `SESSION_BASE_TIMEOUT` from `2000` to `500_000` (0.5 seconds, in microseconds). This gives a maximum total wait of **7.5 seconds** (0.5 + 1 + 1.5 + 2 + 2.5 s), which should cover real-world Mollie latency without risking PHP request timeouts.

```php
private const SESSION_BASE_TIMEOUT = 500_000;  // 0.5 s per step, 7.5 s max total
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
- `tests/PHPUnit/Struct/Address/AddressStructTest.php` – covers the single-word name fallback and the general `createFromApiResponse` paths.

---

## What to keep in mind for future work
- `usleep()` takes **microseconds**; `sleep()` takes seconds. Mixing these units is a silent bug.
- `SESSION_BASE_TIMEOUT` drives `loadSession()` which is also called in `StartCheckoutRoute` (for session reload, not address data). A future optimisation could skip the address-polling loop in that case.
- The `FinishCheckoutRoute` also checks for `billingAddress`. Mollie can return a billing address without `streetAndNumber`; in that case it is silently ignored and shipping is used for billing. This is acceptable but worth knowing.

