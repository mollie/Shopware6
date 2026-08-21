# Review: Correctness

Goal: find bugs and merchant-visible regressions in the change, before tests are written.

## General

- Every new branch: what happens on the path that was *not* taken? Null, empty
  collection, zero, missing extension, absent custom field.
- Every removed or reordered guard: what did it protect?
- Return values that are ignored by the caller.
- Exceptions: thrown where the caller cannot handle them, or swallowed where the merchant
  needs to see the failure. A payment path that silently continues after a failed API call
  is a finding.
- Behaviour change for existing orders/subscriptions created by an older plugin version —
  old data often lacks fields that new code assumes.

## Money

- **Net vs gross.** Mollie is sent gross amounts. A net-priced order (B2B, `taxStatus`
  `net`) must not send the net total for payment, capture, shipment or refund.
- **Rounding.** Line item sums that must match the order total; a rounding-difference line;
  currency decimals other than 2. Comparing floats with `===`.
- **Caps.** A refund must be capped against the *captured* amount, not the authorised or
  ordered one. A capture must be capped against the authorisation.
- **Double action.** Can this refund/capture/cancel run twice — via webhook, via the admin,
  via a flow, via an order return? What makes the second run a no-op?
- Discounts, promotions, vouchers, shipping and negative line items: is the sign right and
  is the line still included?
- Mixed tax rates within one document.

## Shopware context

- **Version / language.** Order and payment-method data is versioned and translated.
  Reading in the wrong version context (live vs. draft) or the wrong language returns
  different or empty values. `customFields` on payment methods are translated.
- **Version merge / clone.** Logic hooked into order writes can fire again when a draft
  version is merged, producing duplicates.
- **Caching.** The product detail page is HTTP-cached — nothing session- or
  customer-specific may be rendered into its markup. Store-API requests have no PHP
  session at all.
- **State machine.** Is the transition valid from the current state? Is a webhook allowed
  to move an already-paid order?
- **Feature flags.** `Feature::isActive()` needs a `has()` guard for flags that do not
  exist in every supported Shopware version.
- **Transactions.** Which order transaction does this action apply to? The last one is not
  always the right one.
- **Sales channel.** Domain path prefixes, multiple domains, headless — do webhook and
  return URLs still resolve to a reachable address?

## Update safety

Merchants update on live shops — a change that only works on a fresh install is a defect.

- **Deprecated or internal Shopware API.** A call into an `@internal` class, a `private`
  service id, or a method already deprecated in the supported range. Name the
  non-deprecated alternative.
- **Migration completeness.** A new or changed DAL field needs a migration — and if
  existing rows need a value, a *data* migration too, not just the schema change.
- **Install / update / uninstall.** Does the change survive a plugin update? Does
  uninstall still clean up, and does it leave merchant data intact where it should?
- **Compatibility surface.** If the change touches the administration (Vue), the
  storefront, a Store-API route or a scheduled task / message queue consumer, say which of
  those a reviewer has to re-check.
- **Config defaults.** A new config field with no default, or a changed default that
  silently alters behaviour for existing shops.

## Extension points

- **Subscriber vs decorator.** Was the right one chosen? A decorator on a core service is
  more likely to break on update; a subscriber gives less control over ordering and data.
  If the change decorates something a plain event could have handled, that is a finding.
- **Priorities and reentrancy.** Does the subscriber priority collide with another plugin's
  expectations? Can the handler trigger the same event again and recurse?

## Security

- Input from a request, a webhook or the Store API that reaches a query, a redirect or the
  Mollie payload without validation.
- A route or admin action without the permission/scope check its siblings have.
- Sensitive data — tokens, mandate ids, card details, customer addresses — written to a log,
  a custom field, or a response that did not carry it before.

## Mollie API

- Field constraints the API enforces silently or by 422: length limits, `E.164` phone
  numbers, required address fields, allowed locales.
- `PATCH` endpoints usually accept only a subset of the fields `POST` accepts.
- Webhook payloads: the status may have moved on again by the time it is read; treat the
  API as the source of truth, not the payload.
- Idempotency: a webhook is delivered more than once.

## Do not report

- Missing tests — tests come after this review.
- Style, naming, design scope — other reviewers cover those.
- Hypotheticals with no reachable caller. If you cannot name how it is triggered, drop it.

## Output

`HIGH | path:line — what breaks — under which input/state`

- **HIGH** — money is wrong, a payment path fails, or merchant data is lost or exposed.
- **MEDIUM** — wrong behaviour in a reachable but narrower case; breaks on plugin update.
- **LOW** — cosmetic or defensive; would not be noticed by a merchant.

