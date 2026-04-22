# Feature: Multi-Subscription Checkout

**Status:** Plan — decisions recorded, ready for implementation.
**Owner:** Vitalij Mik
**Created:** 2026-04-22
**Last updated:** 2026-04-22

---

## Motivation

Customers want to buy several subscription products with **different
intervals** in a single checkout, **mixed** with one-off products and
vouchers. Today the plugin allows only one subscription product per cart,
and the renewal logic blindly clones the original order — which fails as
soon as more than one subscription is involved and which also re-applies
vouchers on every renewal.

In parallel, merchants need to be able to **change product prices** even
when subscriptions are running. Mollie's Subscription API does not support
price updates, so the plugin has to work around it (notify, cancel, create
new).

## Out of scope for this feature

- Migrating Mollie's Orders API → Payments API (tracked separately; this
  feature only consumes Payment / Subscription API objects that already
  exist under `Component/Mollie/Gateway/`).
- Adding new payment methods with subscription support.
- Rewriting the account UI beyond what the new flows require.

---

## Current state (what exists today)

Observed in `shopware/Component/Subscription/`:

- **SubscriptionEntity** (`DAL/Subscription/SubscriptionDefinition.php`)
  holds exactly **one required `order_id`** — a subscription is bound to a
  single order.
- **SubscriptionCartValidator** + **LineItemAnalyzer**: reject carts that
  have more than one subscription product or any mix of subscription and
  non-subscription items. Guest customers and non-subscription-aware
  payment methods are also rejected.
- **CopyOrderService**: on renewal, the full original order is converted
  to a cart and re-created through `CartOrderRoute`. Vouchers /
  promotions are carried along because they live in the same order.
- **WebhookRoute** + **RenewRoute**: lookup a single subscription by its
  Mollie id, call `CopyOrderService::copy`, persist the new transaction,
  update `next_payment_at`.
- **SubscriptionSettings** already expose `reminderDays` and `cancelDays`
  (per sales channel), plus `skipIfFailed`, pause/resume/skip toggles.

The flow is straightforward but hard-wired to the assumption
**1 subscription ↔ 1 original order ↔ 1 renewal order clone**.

---

## Target state

### Checkout

- Products can be added to the cart **mixed**: subscription + one-off +
  vouchers.
- The Mollie product tab in the admin gets a new checkbox **"Can also be
  bought without subscription"**. When enabled, the product-detail page
  renders **two buttons**: "Add to cart" (one-off) and "Subscribe" (adds
  the same product as a subscription line item). When the checkbox is
  off, only the Subscribe button is shown — existing behaviour for
  subscription-only products.
- The storefront template is shipped by the plugin (no theme
  integration required by the merchant).
- Multiple subscription products with **different intervals** are
  allowed in one cart.
- A mixed cart (subscriptions + one-off) still only offers
  **subscription-capable payment methods** — the non-capable methods are
  filtered out as today. Only the cart-level single-subscription lock
  (`MixedCartBlockError`) is removed; the payment-method restriction
  stays.

### First order

- The first payment covers **everything** in the cart (subscription
  products + one-off products + applied vouchers) as a normal one-off
  payment through Mollie.
- After the first payment succeeds, the plugin creates **one Mollie
  subscription per interval group** in the order:
  - Subscription products are grouped by their interval (`1 week`,
    `1 month`, `3 months`, …).
  - One-off products and vouchers/promotions are **not** part of any
    subscription group.
  - Each interval group → one Mollie subscription with the correct amount
    (subscription-products subtotal for that group), description, `times`
    limit (if configured per product), start date.

### Renewal

- When a renewal webhook arrives for a specific Mollie subscription, the
  plugin must resolve **which products** belong to that subscription
  (not the whole original order), then create a **new Shopware order**
  that contains only those products.
- Vouchers, promotions, and one-off products from the original order are
  **not** included in the renewal order.
- Shipping and billing addresses come from the subscription (same as
  today).
- **Shipping costs are recalculated** from the current line-item
  composition — the renewal builds a temporary cart with the
  subscription's line items and lets Shopware's cart logic compute
  shipping (so product-weight-based, free-shipping-threshold and
  similar rules apply naturally).
- Interval granularity is **literal**: `4 weeks` and `1 month` are two
  different groups. We pass Mollie's unit/value as-is, no
  normalisation.
- The `times` limit is fully managed by Mollie. The plugin does not
  stop subscriptions itself; the only case where the plugin touches
  `times` is **pause**, where the remaining repetitions are forwarded
  to Mollie so the count stays correct.
- The new order references the subscription (same `swSubscriptionId`
  custom field) and is tagged (same `SubscriptionTag`).

### Price updates

Mollie subscriptions are immutable in price. Price changes in Shopware
must propagate to running subscriptions. The entire mechanism is a
**new, opt-in feature** — default is disabled, so merchants who do not
configure it keep today's behaviour exactly.

- Before the next renewal, detect that the product price in Shopware
  differs from the price currently stored on the Mollie subscription.
- Notify the **customer by email**. The plugin ships its own mail
  template; merchants can customise it in the standard Shopware admin
  mail template UI.
- The notice window is configured per sales channel via a **new
  dedicated setting** in the subscription settings section (how many
  days before the next renewal the customer has to react). It is
  separate from the existing `reminderDays` and `cancelDays` to avoid
  conflating the general reminder with the price-change reaction
  window.
- If the customer cancels within that window → plain cancel.
- If the window passes without customer action → plugin cancels the
  current Mollie subscription and creates a **new** Mollie subscription
  with the updated price, same interval, same mandate. No further
  customer interaction required.
- If the subscribed product is **deleted** from the catalogue, the
  plugin does **not** cancel the subscription. It notifies the
  **merchant** (so a broken product import can be fixed without
  destroying customer subscriptions). Cancellation only happens if the
  merchant explicitly decides to cancel via the admin.

---

## Big picture change

The core data-model change is **1:1 → 1:N:N**:

- 1 original Shopware order → N Mollie subscriptions (one per interval
  group).
- 1 Mollie subscription → N Shopware orders over time (the renewal
  orders).
- 1 Mollie subscription → M Shopware line items from the original order
  (the ones in its interval group).

This requires tracking **which line items belong to which Mollie
subscription**, which the current schema does not do.

---

## Proposed data model changes

All names are proposals; naming to be finalised during implementation.

### `mollie_subscription` (existing, adjust)

- Keep `order_id` as today. A subscription stays anchored to the order
  that triggered it; the per-product linkage moves to the line-item
  table below.
- Add `interval_unit` (string: `day|week|month|year`) and
  `interval_value` (int). Today this is only on the product / Mollie
  side; having it on the subscription entity makes grouping and pricing
  independent of the product after creation.
- Add `price_update_state` (enum: `none|notified`, default `none`) —
  minimal state machine for the notice-and-migrate workflow. No
  separate cancelling state is needed because Mollie handles the
  cancel-and-recreate atomically when the deadline passes.
- Add `next_notified_price` (float, nullable) — the price the plugin
  will migrate to if the deadline passes.
- Add `notified_at` (datetime, nullable) — timestamp when the customer
  was notified, for deadline calculation.

Note: we intentionally do **not** store `times_remaining`. Mollie is
the source of truth for `times`; the plugin only forwards the value on
pause (so Mollie resumes with the correct count). See decisions
section, point 7.

### `mollie_subscription_line_item` (new aggregate)

New `OneToManyAssociationField` from `SubscriptionEntity`. Populated
for **every** subscription after the data migration has run (see
Migration section). Each row represents one product participating in
one Mollie subscription:

- `subscription_id` (fk)
- `product_id` (fk, `PRODUCT::class`)
- `product_version_id`
- `original_order_line_item_id` (fk, `ORDER_LINE_ITEM`) — the line item
  from the first order this was bought on.
- `quantity` (int)
- `unit_price` (float) — the price Mollie currently bills. Separate
  from the product's current price on purpose (that's the whole
  price-update workflow).
- `label`, `payload` (json, for refs like product options).

Deletion rule for products: when a referenced product is deleted, the
subscription continues to run. The plugin emits a **merchant-facing
notification** (admin inbox / log entry) so the merchant can decide —
the typical case is a failed catalogue import, not an intentional
removal. No automatic customer-side action.

### `mollie_subscription_history` (existing)

No structural change. Continue to log transitions. New transition
values will be introduced (e.g. `price_notified`, `price_migrated`).

### `order` custom fields

- `mollie_payments.swSubscriptionId` → keep, but it now **may contain a
  list** of subscription ids for orders that seeded multiple
  subscriptions (the first order after a mixed checkout). Proposal:
  rename-in-place to `swSubscriptionIds[]` and keep a one-element array
  for backward compatibility.

---

## Proposed component changes

### New / heavily reshaped

- **SubscriptionGrouper** (new) — given an order or cart, return groups
  keyed by `(interval_unit, interval_value)`, filtered to subscription
  products only. Pure function, unit-testable.
- **CreateSubscriptionsFromOrder** (new action) — after the first
  payment is paid, iterate groups and create one Mollie subscription per
  group via `SubscriptionGateway`, persist rows in `mollie_subscription`
  and `mollie_subscription_line_item`.
- **RenewalOrderBuilder** (replaces the "clone the whole order"
  behaviour in `CopyOrderService`) — build a **new** cart that contains
  only the line items registered against a given Mollie subscription,
  recalculate taxes/shipping based on current settings, convert to
  order.
- **PriceDriftDetector** (new) — scheduled task: for each active
  subscription, compare the stored `unit_price` × quantity sum against
  the current product price. Transitions state to `notified`, writes
  `next_notified_price`, dispatches an event for the mail channel.
- **PriceMigrationHandler** (new) — when `cancelDays` has elapsed since
  `notified`, cancel the Mollie subscription and create a new one with
  the updated price. Copies all other fields (mandate, description,
  interval).

### To drop

- **CopyOrderService**: replaced entirely by `RenewalOrderBuilder`.
  After the data migration has populated `mollie_subscription_line_item`
  for existing subscriptions, no call site still needs the
  whole-order clone. Delete the class in the same release.

### To trim

- **SubscriptionCartValidator**: the mixed-cart and single-subscription
  rules are removed. Guest, payment-method-capability checks stay (a
  mixed cart still only offers subscription-capable payment methods).
- **LineItemAnalyzer**: `hasMixedLineItems` is no longer used for
  blocking; it's either removed or repurposed as an informational
  helper. `hasSubscriptionProduct` stays.

### To adjust

- **WebhookRoute** / **RenewRoute**: route to the correct Mollie
  subscription (now one of N per original order) and always use the
  new `RenewalOrderBuilder`. Same URL pattern.
- **SubscriptionDataService**: load the subscription with its
  `lineItems` aggregate and the original order (read-only reference).
- **Pause handler**: when pausing a subscription, forward the remaining
  `times` value to Mollie so Mollie's internal counter stays correct
  when the subscription is resumed. This is the only place the plugin
  touches `times`.

---

## Settings (plugin config)

Already there: `reminderDays`, `cancelDays`, `skipIfFailed`,
`allowPauseAndResume`, `allowSkip`, `showIndicator`, `allowEditAddress`.

New (all **opt-in**; default state leaves current behaviour unchanged):

- `priceUpdateEnabled` (bool, default `false`) — master switch for the
  whole price-update workflow.
- `priceUpdateNoticeDays` (int, default `0`) — days before the next
  renewal the customer has to react to a detected price change. Zero
  while the feature is off.

Mail template: ships as a new standard Shopware mail template so the
merchant can customise subject/body in the admin.

Product-level setting (not a plugin setting — lives on the product's
Mollie tab):

- `mollieAllowStandalonePurchase` (bool, default `false`) — controls
  whether the product detail page shows both "Add to cart" and
  "Subscribe" buttons. When off: Subscribe-only (today's behaviour for
  subscription products).

---

## Phases / work packages

Stages are ordered; each can be a separate PR. Numbers are a guide, not
fixed.

### Phase 1 — Remove cart lock, enable mixed carts (no new schema yet)

- Update `LineItemAnalyzer` / `SubscriptionCartValidator` to stop
  rejecting mixed carts.
- Add the "Buy once / Subscribe" storefront control (two-button
  variant). Clarify who owns the template change — see open question.
- Keep the existing single-subscription copy flow temporarily.

### Phase 2 — New data model + backfill

- Schema migration: add new columns on `mollie_subscription`, add
  `mollie_subscription_line_item` table.
- Add fields + `OneToManyAssociationField` on the definitions.
- Data migration: populate `mollie_subscription_line_item` from the
  original order for every **active or paused** subscription;
  cancelled and completed subscriptions are skipped (see Migration
  section). Plain Doctrine migration for small shops; chunked CLI
  command for larger ones. Runs once, idempotent.

### Phase 3 — Multi-subscription creation after first payment

- `SubscriptionGrouper` + `CreateSubscriptionsFromOrder`.
- Hook into the existing `Pay`/`Finalize` success flow so subscriptions
  are created after payment success.
- Persist `mollie_subscription` + `mollie_subscription_line_item` rows.

### Phase 4 — Per-subscription renewal order

- `RenewalOrderBuilder` using subscription line items instead of the
  original order.
- Replace `CopyOrderService` calls in `RenewRoute` (all renewals now
  go through the new path thanks to the Phase 2 backfill).
- Remove voucher/promotion carry-over.
- Delete `CopyOrderService`.

### Phase 5 — Price update workflow

- `PriceDriftDetector` scheduled task + `priceUpdateMode` setting.
- Email template + account-page surface for the notice (TBD).
- `PriceMigrationHandler` that cancels + re-creates on deadline.
- New history transitions (`price_notified`, `price_migrated`).

### Phase 6 — Clean-up

- Remove dead validator code, dead config keys, dead snippets.
- Update Behat scenarios for the new flows.

---

## Tests

- **Unit:** `SubscriptionGrouper`, `RenewalOrderBuilder` payload logic,
  `PriceDriftDetector` comparison logic, updated `LineItemAnalyzer`.
- **Integration:** `CreateSubscriptionsFromOrder` against Mollie sandbox
  (real subscriptions), `PriceMigrationHandler` full lifecycle,
  `RenewalOrderBuilder` round-trip through `CartOrderRoute`. Uses the
  existing `SubscriptionTestBehaviour` trait.
- **Behat:** new feature files:
  - `mixed-cart-checkout.feature` — mixed cart pays once, creates N
    subscriptions.
  - `subscription-renewal-per-interval.feature` — per-interval renewal
    produces the right line-items.
  - `subscription-price-update.feature` — price changes trigger notice
    and, after deadline, auto-migration.

Today's `subscription.feature` needs to be audited: some scenarios
assume the cart lock and must be rewritten.

### Renewal testing workaround

Mollie's sandbox does **not** let us trigger a renewal payment from
the outside — we cannot fake the recurring payment event. The test
strategy therefore uses a detour:

1. Create a subscription (first order, real sandbox flow).
2. In parallel, place a plain (non-subscription) order for the same
   customer. This produces a real Mollie payment id.
3. Feed that payment id into the renewal webhook as if it belonged to
   the subscription.

The renewal route already has the hook for this: when the plugin is
running in **dev mode**, the check that ties the payment id to the
subscription id (`$molliePayment->getSubscriptionId() !==
$subscription->getId()`) is skipped. See
`shopware/Component/Subscription/Route/RenewRoute.php`. Behat
scenarios that exercise renewals rely on this path; production code
still enforces the check.

---

## Migration of existing data

The data model switches cleanly to the new shape — **no v1/v2
branching in runtime code**. A one-time data migration moves every
**still-running** subscription onto the new aggregate so that after
the update every renewal goes through `RenewalOrderBuilder`.

Scope — what gets backfilled:

- **Active** subscriptions (the ones Mollie will still trigger
  renewals for).
- **Paused** subscriptions (they may be resumed later and then need
  the new aggregate).

Scope — what is **skipped**:

- **Cancelled** subscriptions. Mollie will never renew them, so
  there is nothing for the plugin to do with their line items.
- **Completed** subscriptions (reached their `times` limit). Same
  reasoning: no future renewal.

Skipping these two status groups keeps the migration cheap on large
shops and avoids carrying product references that are no longer
relevant. Reporting and history stay intact because the parent
`mollie_subscription` row and its `mollie_subscription_history`
aggregate are untouched.

Mechanics:

- **Schema migration:** add the new columns on `mollie_subscription`
  (`interval_unit`, `interval_value`, `price_update_state`,
  `next_notified_price`, `notified_at`) and create the
  `mollie_subscription_line_item` table.
- **Data migration:** for each in-scope `mollie_subscription` row
  (status = active or paused), read its original order (`order_id`)
  and create one `mollie_subscription_line_item` row per
  subscription product in that order. Fields copied:
  - `product_id`, `product_version_id`, `quantity`, `unit_price`,
    `label`, `payload` — from the original order line item.
  - `original_order_line_item_id` — the original line item id, so the
    link back to the seeding order is preserved.
  - `interval_unit` / `interval_value` on the parent subscription —
    derived from the product's Mollie custom fields at migration
    time (same source the plugin uses today to create the Mollie
    subscription).
- **Result:** after the migration runs, every still-running
  `mollie_subscription` row has its `lineItems` aggregate populated,
  and the renewal route unconditionally uses `RenewalOrderBuilder`.

Scale: the migration has to handle shops with 10k+ running
subscriptions. It runs as a plain Doctrine migration by default but
falls back to a chunked CLI command (`bin/console
mollie:subscriptions:migrate-line-items --chunk=500`) for large shops,
so it can be resumed if interrupted. The CLI command is idempotent
(skips rows that already have line items and rows with
cancelled/completed status).

Edge cases:

- **Subscription whose original order line item points to a deleted
  product** — the migration still creates the row with whatever
  product data is available (label from order line item, `unit_price`
  from the order). The merchant notification logic (see Data model
  section) kicks in on the next `PriceDriftDetector` run.
- **Subscription cancelled between schema migration and data
  migration run** — the CLI skips it on its status filter, so no
  extra handling needed.
- **Mixed-cart orders created before the feature shipped** do not
  exist (the cart lock used to forbid them), so no special handling
  is required for that case.

`CopyOrderService` is removed in the same release once the migration
is in place. Tracked in
[`../packages/subscription.md`](../packages/subscription.md).

---

## Decisions

These were the open questions during drafting. All have been resolved
with the product owner — the answers drive the body of this document.

1. **Storefront two-button UI** → plugin ships the template. A new
   checkbox on the product's Mollie admin tab
   (`mollieAllowStandalonePurchase`) toggles whether the product
   detail page shows "Add to cart" + "Subscribe" or "Subscribe"
   only. No theme integration required from the merchant.
2. **Price-change notification channel** → email only. The plugin
   ships a standard Shopware mail template, so the merchant can
   customise subject and body in the admin.
3. **Deadline clock** → a new, dedicated setting
   `priceUpdateNoticeDays` (plus the master switch
   `priceUpdateEnabled`). Not reused from `cancelDays` /
   `reminderDays`, so the two concepts stay separated. The whole
   price-update workflow is opt-in; default-off preserves current
   behaviour.
4. **Deleted products** → notify the **merchant**, do not
   auto-cancel. A deletion is usually an import mistake, and
   forcibly cancelling customer subscriptions would destroy
   revenue. The merchant decides explicitly in the admin.
5. **Interval normalisation** → none. `4 weeks` and `1 month` are
   distinct groups. Mollie's unit/value is passed through literally.
6. **Shipping cost on renewal** → recalculated. The renewal builds a
   temporary Shopware cart from the subscription's line items and
   lets Shopware's cart logic derive shipping, so weight-based and
   free-shipping-threshold rules apply naturally.
7. **`times` / maximum purchases** → fully managed by Mollie. The
   plugin does not count down repetitions itself. The only place it
   touches `times` is the pause handler, which forwards the
   remaining count so Mollie's counter stays correct on resume.
8. **"First-month bonus" one-off items** → not in scope for the MVP.
   One-off items are always billed on the first order only.
9. **Subscription-specific vouchers** → not supported. Vouchers
   apply to the first order only, never to renewals.
10. **Cart lock removal vs. payment methods** → only the cart-level
    single-subscription lock (`MixedCartBlockError`) is removed. The
    payment-method capability check stays: a mixed cart still only
    offers subscription-capable payment methods.
11. **Behat coverage for multi-subscription creation** → multi-
    subscription checkout scenarios run in CI by default, using the
    real Mollie sandbox via `MolliePage`.
12. **Existing subscriptions** → backfilled by a one-time data
    migration so that after the update every still-running
    subscription uses the new `mollie_subscription_line_item`
    aggregate and the new renewal path. **Only active and paused
    subscriptions** are backfilled — cancelled and completed
    subscriptions are skipped (no future renewal, no need for the
    aggregate). No v1/v2 coexistence. For large shops (>10k
    subscriptions) a chunked, idempotent CLI command is provided as
    an alternative to the plain Doctrine migration. See Migration
    section.

### Renewal testing in CI

Not a product decision but a test-infrastructure note that belongs
with the decisions above: Mollie's sandbox does not emit renewal
payments, so Behat relies on the dev-mode bypass in
`shopware/Component/Subscription/Route/RenewRoute.php` — in dev mode
the subscription-id-vs-payment-id check is skipped, so a payment id
from a parallel non-subscription order can be fed into the renewal
webhook. See the Tests section for the exact workaround.

---

## References

- `shopware/Component/Subscription/` — current implementation.
- `shopware/Component/Subscription/Route/WebhookRoute.php`,
  `RenewRoute.php` — current renewal path.
- `shopware/Component/Subscription/CopyOrderService.php` — current
  whole-order clone.
- `shopware/Component/Settings/Struct/SubscriptionSettings.php` —
  existing toggles.
- `tests/Behat/Features/subscription.feature` — existing Behat
  coverage.
- `../packages/subscription.md` — refactor progress tracking for this
  package.
