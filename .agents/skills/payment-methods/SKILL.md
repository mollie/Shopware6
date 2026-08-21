---
name: payment-methods
description: Add or remove a Mollie payment method in this Shopware 6 plugin. Adding = new enum case plus a handler with marker interfaces (manual capture, bank transfer, B2B-only, subscriptions, recurring mandate, Orders API); registration is automatic, no migration or icon needed. Removing = add DeprecatedMethodAwareInterface so the method is deactivated but stays in the shop, keeping old orders intact — never delete a handler. Use when asked to add, implement, register, remove, deprecate or disable a payment method such as Blik, Twint, Pay by Bank, Payconiq, Trustly.
---

# Skill: Add or Remove a Mollie Payment Method

## Adding

A standard method is **four files, ~25 lines**. Registration is automatic — the tag
`mollie.payment.method` sits on `AbstractMolliePaymentHandler`, `PaymentHandlerLocator`
collects it, `PaymentMethodInstaller` upserts it on install and update.

### 1. Ask all of this in one message

API name (`blik`, `paybybank`), display name, and yes/no for: manual capture · bank
transfer style · B2B only · subscriptions · rechargeable from a stored mandate · needs the
Orders API instead of the Payments API · allowed in payment links · Behat-testable (see 4).

**If it is not a standard Payments-API method** — an express flow, a terminal, a wallet with
its own SDK, anything needing extra checkout UI — stop and say what the special integration
would need. This skill does not cover it.

### 2. `shopware/Component/Mollie/PaymentMethod.php`

Add `case PAY_BY_BANK = 'paybybank';` alphabetically. The value is the API name and also
drives the technical name and the icon URL — a typo means a silently missing icon.

Add the case to `isSupportedForPaymentLink()` only if payment links were confirmed. The
`default => false` is deliberate: an unsupported method in `allowedMethods` makes Mollie
reject the whole payment-link request. Leave `eInvoicePaymentMeansCode()` alone unless it is
a card (48), bank transfer (58) or direct debit (59).

### 3. `shopware/Component/Payment/Method/<Name>Payment.php`

Copy `BlikPayment.php` — it is the minimal case — and change the class name, the enum case
and the return of `getName()`. Nothing else: no constructor, and
`applyPaymentSpecificParameters()` only if the method needs extra payload fields, which is
worth raising before writing.

Marker interfaces from `Component\Payment\Handler\`, added per answer:

| Answer | Interface | Effect |
|---|---|---|
| manual capture | `ManualCaptureModeAwareInterface` | payload gets `CaptureMode::MANUAL` |
| bank transfer | `BankTransferAwareInterface` | due date from the `dueDateDays` setting; `process`/`in_progress` instead of `processUnconfirmed`/`unconfirmed`; no pending-order session key, so browser-back does not hit the edit-order form |
| B2B only | `BusinessCustomerAwareInterface` | hidden unless the billing address has a company |
| subscriptions | `SubscriptionAwareInterface` | keeps `SequenceType::FIRST`. **Without it nothing fails** — the payload drops to `ONEOFF` and the subscription never gets a mandate |
| stored mandate | `RecurringAwareInterface` | may reuse a mandate and set `SequenceType::RECURRING` |
| Orders API | `OrdersApiAwareInterface` | `Pay` routes to `executeOrdersApi()` |

Exact spelling: `BusinessCustomer…`, `Subscription…` singular, all with the `Interface`
suffix. `TestOnlyAwareInterface` (never installed) and `DeprecatedMethodAwareInterface`
(installed inactive) exist but are normally not wanted.

### 4. Tests — after the review, not before

**Unit:** no new file. `tests/Unit/Payment/Method/PaymentMethodsTest.php` covers all
handlers — add the `use` import, a `#[CoversClass]` attribute, and one row in
`providePaymentMethods()`. If the method carries marker interfaces, add one
`assertInstanceOf` test in the style of the Apple Pay / Card tests further down that file.

**Behat:** `tests/Behat/Features/payment.feature` creates real orders against the Mollie
API. Add one row to the `payment success` Examples table:

```
| paybybank | MOL_REGULAR | 1 | paid | DE | EUR |
```

- `paymentStatus` is `authorized`, not `paid`, for manual-capture methods (billie, klarna,
  riverty).
- Country and currency must be ones the method actually supports — `blik`/`przelewy24` PLN,
  `swish` SEK, `twint` CHF, `vipps` NOK/NO, `mobilepay` DKK/DK, `alma` FR, `bizum` ES.
- Methods needing a JS-rendered form (**creditcard**), a physical terminal (pointofsale) or
  a wallet SDK (applepay) **cannot** be Behat-tested — skip the row and say why. Also
  currently absent: bacs, directdebit, payconiq, paysafecard, trustly, voucher.
- A method with non-standard flow gets its own `Scenario` instead of a table row — see
  `billink` (private address) and `wero` (pending → unconfirmed).

Behat needs a Mollie API key and does not run on fork pull requests.

### 5. Not needed

No icon file (fetched from Mollie's CDN via the API name), no technical name (derived), no
migration (id is hashed from the technical name), no `services.xml`, no snippet, no
`config.xml`. Say so in your answer so nobody looks for them.

Changelog: one `Hinzugefügt`/`Added` line per file — `AGENTS.md` section 6.

### Expected diff

```
shopware/Component/Mollie/PaymentMethod.php         +1 (+1 for payment links)
shopware/Component/Payment/Method/XPayment.php      new, ~20 lines
tests/Unit/Payment/Method/PaymentMethodsTest.php    +3
tests/Behat/Features/payment.feature                +1
CHANGELOG_de-DE.md / CHANGELOG_en-GB.md             +1 each
```

More than this needs a reason stated in your answer.

## Removing

**Never delete the handler or the enum case.** Existing orders resolve their payment method
through both — the order detail page, refunds and shipments break without them.

Add `DeprecatedMethodAwareInterface` to the handler. That is the whole code change:

```php
final class PayconiqPayment extends AbstractMolliePaymentHandler implements DeprecatedMethodAwareInterface
```

`PaymentMethodInstaller` forces `active => false` on install and on every update. This
overrides the branch that otherwise preserves the merchant's setting, so a shop with the
method enabled loses it on the next update — intended. The row stays in the database.

Examples: `PayconiqPayment`, `TrustlyPayment`, `DirectDebitPayment`,
`KlarnaOrdersApiPayment`, `PayPalOrdersApiPayment`.

- **Keep** the `providePaymentMethods()` row — old transactions carry the API name in the
  `mollie_payment_method_name` custom field.
- **Remove** the `tests/Behat/Features/payment.feature` row — a deactivated method cannot
  complete a checkout.
- **Decide** on `isSupportedForPaymentLink()`. Deprecating does not drop it: `TRUSTLY` is
  deprecated and still listed. Remove it and say so, or leave it and say so.
- Changelog: `Geändert` / `Changed`, e.g. *"Die Zahlungsart X wird nicht mehr angeboten;
  bestehende Bestellungen bleiben unverändert."*

### Expected diff

```
shopware/Component/Payment/Method/XPayment.php   +2
tests/Behat/Features/payment.feature             -1 if a row existed
CHANGELOG_de-DE.md / CHANGELOG_en-GB.md          +1 each
```
