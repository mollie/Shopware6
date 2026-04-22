# Release Plan

Timeline for the `src/` → `shopware/` refactor and the matching
plugin version releases. This file is the single source of truth
for "what lands when". The per-feature and per-package files stay
focused on technical detail; this file coordinates them.

**Hard deadline:** **5.0.0 must ship by the end of Q2 2026
(2026-06-30).**

---

## Current state (2026-04-22)

- **Released:** `5.0.0-beta.2`.
- **In planning:** `5.0.0-beta.3`.
- **Test coverage:** 18.1 % (statements), 18.6 % (methods) on
  the new `Mollie\Shopware\*` namespace. See
  [`index.md`](index.md) for the scope definition.
- **`src/` status:** still populated. Route migration is the
  most visible indicator — see the "Migration status via routes"
  section in [`index.md`](index.md).

---

## Timeline

| Version           | Target date       | Scope                                                                                                      | Coverage target |
|-------------------|-------------------|------------------------------------------------------------------------------------------------------------|-----------------|
| `5.0.0-beta.2`    | Released          | Baseline for 5.0.0 series.                                                                                 | 18.1 %          |
| `5.0.0-beta.3`    | Mid Q2 2026       | Subscriptions feature; Express checkout address sync; focused test expansion (unit + integration + Behat). | ~40 %           |
| `5.0.0-beta.4`    | Late Q2 2026      | PayPal Express Orders-API bridge; Refunds feature; continued test expansion.                               | ~50 %           |
| **`5.0.0`**       | **End Q2 2026 — hard deadline** | Stabilisation only; no new features. Release gate.                                             | **~60 %**       |
| `5.1.0`           | End Q3 2026       | Payment Links for admin-created orders; continued `src/` emptying; `vendor_manual/` removed.               | **~80 %**       |
| `5.x` and beyond  | Q4 2026+          | Test ratcheting + CI gates, once the discipline is in place.                                               | 80 %+           |

Dates are intentions, not contracts — except the `5.0.0` hard
deadline. Coverage numbers are directional.

---

## `5.0.0-beta.3` — Subscriptions + Express addresses

**Scope:**

- Land the
  [Multi-Subscription Checkout](features/subscriptions.md) feature
  in full: mixed carts, multiple subscriptions per order,
  per-interval renewal orders, price-update workflow, backfill
  migration for active + paused subscriptions.
- Land the
  [Express Checkout — Address & Guest Account Sync](features/express-checkout-address-sync.md)
  feature in full: shared `AddressSynchronizer`, PayPal Express
  parity with Apple Pay, MD5-hash extension (phone + company),
  integration tests covering logged-in-customer default-address
  update.
- Expand test coverage on the new namespace toward ~40 %.
  Priority — in rough order of leverage:
  1. Wave 1 (entities, structs, DTOs, small exceptions, small
     events) to close the largest pool of easy coverage.
  2. Integration tests for any class touched by the two features
     above.
  3. Behat scenarios that back the two features.
- No `src/` removal beyond what these features naturally obsolete.

**Exit criteria:**

- Subscriptions and Express-address features integration-green
  against the Mollie sandbox.
- Coverage ≥ ~40 % on `Mollie\Shopware\*`.
- Beta changelog drafted in `CHANGELOG_de-DE.md` /
  `CHANGELOG_en-GB.md`.

---

## `5.0.0-beta.4` — PayPal Express + Refunds

**Scope:**

- Land the
  [PayPal Express — Temporary Orders-API Bridge](features/paypal-express-orders-api.md)
  in full: `OrdersApiAwareInterface` marker,
  `CreatePaymentBuilder` → `PayloadBuilder` rename with
  `buildPayment` / `buildOrder`, `MollieGateway::createOrder()`.
  PayPal Express keeps working on Orders API while every other
  handler is free to migrate to Payments API.
- Land the [Refunds on Payments API](features/refunds.md) feature
  in full: Payments-API refund gateway, Orders-API fallback via
  stored custom fields for legacy orders, Mollie tab in the order
  detail, Vue decomposition, consolidated overview route,
  line-item refund tracking via
  `order_line_item.customFields.mollie_payments.refunds`.
- Continue test expansion toward ~50 %. Wave 2 (business-critical:
  payment actions, subscription actions, transaction, Mollie
  gateway extensions, routes with fake dependencies) dominates.
- Begin emptying `src/` where low-risk: classes that the refunds
  feature obsoletes (legacy refund manager internals) and
  anything the Vue decomposition retires.

**Exit criteria:**

- PayPal Express Behat green, order fingerprint confirms `ord_*`
  on the order and not `tr_*` on the transaction.
- Refunds: admin-to-sandbox refund round-trip green on both
  legacy (Orders API) and new (Payments API) paths.
- Coverage ≥ ~50 %.
- Beta changelog updated.

---

## `5.0.0` — Release

**Scope: stabilisation only.** No new features. The release gate
is the last chance to catch regressions introduced by beta.3 /
beta.4.

**Exit criteria — all must hold:**

- Subscriptions, Express-address, PayPal Express Orders-API
  bridge, Refunds — all in their "Plan — decisions recorded"
  target states, merged, covered by unit + integration + Behat
  tests.
- Coverage ≥ 60 % on `Mollie\Shopware\*` (statements).
- No regression in the existing Behat happy paths.
- `CHANGELOG` finalised, release notes drafted.
- Migration notes for merchants documented.

**Explicitly deferred past 5.0.0:**

- Payment Links for admin-created orders → 5.1.0.
- `src/` final emptying → 5.1.0 (continues).
- `vendor_manual/mollie-api-php` removal → 5.1.0.
- CI coverage gates / ratcheting → post-5.1.0.

---

## `5.1.0` — Payment Links + finish the `src/` removal

Target: end of Q3 2026.

**Scope:**

- Land [Payment Links for Admin-Created Orders](features/payment-links.md)
  in full: gateway (`createPaymentLink`, `deletePaymentLink`,
  `getPaymentLinkById`), insert + update subscribers on
  `order.written` with the combined
  `createdById !== null` + `AdminApiSource` signal, shared
  webhook endpoint with `pl_*` fallback inside
  `MollieGateway::getPaymentByTransactionId()`, plugin-config
  section with `enabled` + `linkLifetimeDays`.
- Continue removing classes from `src/` per
  [`packages/*.md`](packages/). Target: `src/` **empty** by end
  of Q3 2026.
- Remove `vendor_manual/mollie-api-php` once no code references
  it. All gateway traffic stays on the Guzzle path (precedent:
  Payment Links gateway, Refunds Payments-API gateway).
- Retire `tests/PHPUnit/` once every corresponding class has
  either moved to `shopware/` or been deleted — this is the
  second "done criterion" for the refactor (see
  [`index.md`](index.md), "Done criteria for the refactor").

**Exit criteria:**

- Payment Links Behat green, admin-order-to-paid round-trip
  without any shop login.
- `src/` directory empty.
- `vendor_manual/mollie-api-php` deleted.
- `tests/PHPUnit/` deleted.
- Coverage ≥ 80 % on `Mollie\Shopware\*` (statements).

---

## `5.x` and beyond

Once coverage is above 80 % and the refactor is done (both
`src/` and `tests/PHPUnit/` gone), wire up the CI discipline:

- Per-package coverage floors (start at current number, never
  go down).
- Branch protection requiring the unit + integration testsuites
  to pass.
- Mutation testing on the Wave-1 / Wave-2 classes to surface the
  coverage that's "green but useless".

Scope beyond that is explicitly open — features worth
considering (no commitment):

- Standalone payment links not tied to an order (merchant UI).
- Reminder / dunning mails with payment links.
- Apple Pay Direct namespace migration to
  `shopware/Component/Payment/ApplePayDirect/` (see
  [`express-checkout-address-sync.md`](features/express-checkout-address-sync.md),
  Decision 4).

---

## Feature → release mapping

| Feature                                                                             | Lands in     | Blocker for release? |
|-------------------------------------------------------------------------------------|--------------|----------------------|
| [Multi-Subscription Checkout](features/subscriptions.md)                            | `beta.3`     | 5.0.0                |
| [Express Checkout — Address & Guest Account Sync](features/express-checkout-address-sync.md) | `beta.3`     | 5.0.0                |
| [PayPal Express — Temporary Orders-API Bridge](features/paypal-express-orders-api.md) | `beta.4`     | 5.0.0                |
| [Refunds on Payments API](features/refunds.md)                                      | `beta.4`     | 5.0.0                |
| [Payment Links for Admin-Created Orders](features/payment-links.md)                 | `5.1.0`      | 5.1.0                |

---

## Dependencies and ordering constraints

- **Refunds → depends on Payments-API switch.** The refund
  gateway's primary path is
  `POST /v2/payments/{id}/refunds`. Orders are still being
  created through the Orders API for any handler that hasn't
  migrated yet. The refund feature therefore needs the
  Orders-API fallback (already decided in
  [`refunds.md`](features/refunds.md)) to cover legacy
  orders — the fallback can stay indefinitely.
- **PayPal Express → blocks the general Payments-API
  migration.** Without the `OrdersApiAwareInterface` carve-out,
  PayPal Express breaks as soon as any handler is forced onto
  Payments API. beta.4 ships the carve-out; every other handler
  can then migrate freely across the remaining beta.4 → 5.0.0
  window and into 5.1.0.
- **Payment Links → blocks on Refunds' custom-field work.**
  Payment Links store `payment_link_id` on the transaction
  custom fields. The refunds feature's resolver already
  reads multiple keys from the same custom-field block
  (`payment_id`, `order_id`). Building on that pattern after
  5.0.0 keeps the custom-field schema consistent.
- **`src/` emptying → blocks on every feature above.** Each
  feature retires part of `src/`. `vendor_manual/` removal only
  becomes possible when no `src/` class still uses the SDK.
  This is why the emptying is explicitly scheduled for 5.1.0,
  not 5.0.0 — the beta.4 feature burn leaves `src/` partially
  populated on purpose.
- **CI coverage gates → blocks on ≥ 80 % coverage.** Gates are
  introduced after 5.1.0 so the ratchet starts from a healthy
  number rather than locking in the current 18.1 %.

---

## Updating this document

- **After each beta tag:** move the "Current state" timestamp
  and coverage number; mark the beta as Released in the timeline
  table.
- **Whenever a feature's scope changes:** update the
  corresponding per-feature file
  ([`features/*.md`](features/index.md)) first, then reflect any
  release-relevant shifts here. Do not duplicate decision
  detail — link to the feature file.
- **If a feature slips a release:** move it in the timeline
  table and add a short note in the "Feature → release mapping"
  section explaining why. Never silently re-shuffle.
- **Coverage targets are guidance, not contracts.** If the
  actual coverage at a milestone differs, update the number
  rather than the story around it — the story follows the
  reality.

---

## References

- [`index.md`](index.md) — overall refactor overview, route
  migration progress, package index, coverage definition.
- [`features/index.md`](features/index.md) — one file per
  feature in flight.
- [`packages/`](packages/) — per-package refactor and coverage
  status, 15 files.
- [`rules/test-strategy.md`](rules/test-strategy.md) — when to
  write unit vs. integration vs. Behat.
- `CHANGELOG_de-DE.md`, `CHANGELOG_en-GB.md` — public
  release notes (updated per beta tag).
