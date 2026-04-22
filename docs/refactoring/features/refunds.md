# Feature: Refunds on Payments API

**Status:** Plan — decisions recorded, ready for implementation.
**Owner:** Vitalij Mik
**Created:** 2026-04-22
**Last updated:** 2026-04-22

---

## Motivation

The current refund manager is wired to the Mollie **Orders API** (see
`src/Service/Refund/RefundService.php` + `src/Components/RefundManager/`).
As the plugin moves to the Payments API, refunds must switch to
`POST /v2/payments/{paymentId}/refunds`. Orders-API refunds only work
for orders that were originally created via the Orders API, so a
simple cut-over would break refunds for any already-paid order on a
shop that updates the plugin.

In parallel, two long-standing pain points need fixing with the
rewrite:

- The admin UI is one 728-line Vue component
  (`mollie-refund-manager/index.js`) that mixes data-loading, item
  selection, grid rendering, refund submission, status polling and
  promotion-handling. It is hard to test and hard to extend.
- The plugin currently extends `sw-order-detail-general` directly.
  Any JavaScript error in the Mollie extension takes the whole
  order-detail view down — the merchant can't even see the order
  anymore. The shipping and cancel-items features ride on the same
  entry point and suffer the same fate.
- Opening an order today triggers **multiple** admin-api calls
  (`refund-manager/data`, `refund/list`, `refund/total`, plus ship /
  cancel-items calls), making the order-detail page noticeably
  slower.

## Out of scope for this feature

- Subscription refunds (tracked separately; subscriptions use Mollie
  subscriptions, not payment-refunds).
- Payment-method-specific refund restrictions beyond what Mollie
  already enforces on the API.
- Credit-note generation changes — `RefundCreditNoteService` stays as
  today.
- Account / storefront UI changes.

---

## Current state (what exists today)

Observed in the plugin:

- **PHP:** `src/Components/RefundManager/RefundManager.php` (526 LOC)
  orchestrates refunds. `src/Service/Refund/RefundService.php` calls
  Mollie via `OrderRefundEndpoint` (Orders API) and falls back to
  `PaymentRefundEndpoint` (Payments API) when the order has no
  Mollie-order id.
- **Admin-api routes** (`src/Resources/config/routes/admin-api/refund.xml`,
  controller `src/Controller/Api/Order/RefundControllerBase.php`):
  - `POST /api/_action/mollie/refund-manager/data` → bundle of
    refundability + UI data
  - `POST /api/_action/mollie/refund/list`
  - `POST /api/_action/mollie/refund/total`
  - `POST /api/_action/mollie/refund` — create
  - `POST /api/_action/mollie/refund/cancel`
- **Mollie id storage:** `customFields.mollie_payments` JSON on the
  Shopware order, exposed via `OrderAttributes`:
  `getMollieOrderId()`, `getMolliePaymentId()`. Also mirrored on
  `order_transaction.custom_fields` by `UpdateOrderTransactionCustomFields`.
- **Vue:** `src/Resources/app/administration/src/module/mollie-payments/`
  - `components/mollie-refund-manager/index.js` — 728 LOC, the one
    big component.
  - Sibling components `mollie-ship-order` and `mollie-cancel-item`
    under the same module.
  - Hook: `extension/sw-order/view/sw-order-detail-general/index.js`
    overrides `sw-order-detail-general` and injects Mollie services.
- **Tests:** `tests/PHPUnit/Components/RefundManager/*`,
  `tests/PHPUnit/Service/Refund/*`, `tests/Cypress/e2e/storefront/refund/`.

The flow works but is tightly coupled to Orders-API refunds and to a
single monolithic Vue component embedded in Shopware's core order
view.

---

## Target state

### API — which refund endpoint to call

The plugin must call one of two Mollie endpoints depending on **how
the order was originally created in Mollie**:

| Original Mollie object          | Refund endpoint (target)                        |
|---------------------------------|-------------------------------------------------|
| Payment (new Payments-API flow) | `POST /v2/payments/{paymentId}/refunds`         |
| Order   (legacy Orders-API flow)| `POST /v2/orders/{orderId}/refunds`             |

Decision driver is the **Mollie id stored in the right place**:

- `order.customFields.mollie_payments.order_id` → legacy Orders-API
  refund. This custom field only exists on orders that were created
  through the Orders API.
- `order_transaction.customFields.mollie_payments.payment_id` →
  Payments-API refund. This is set by the new payment flow on the
  **current** (i.e. paid / authorised) transaction.

Important detail: the Mollie payment id lives on the **transaction**,
not on the order. A single Shopware order can have multiple
transactions (e.g. the customer first picks Mollie, cancels, then
pays with a non-Mollie method). The refund logic always targets the
current / last successful transaction, and if that transaction is not
a Mollie one, no refund UI is offered at all (see tab visibility
below).

Resolver rules in order:

1. If `order.customFields.mollie_payments.order_id` is set → Orders
   API.
2. Else, look at the current order transaction's custom field
   `mollie_payments.payment_id` — if set → Payments API.
3. Else → no Mollie refund possible. The refund button is disabled
   with an explanatory tooltip (very old orders or orders whose
   current transaction is not Mollie).

We do **not** run a data migration. The existing custom fields on
legacy orders are authoritative and stay untouched. New orders never
write `order_id`; they only get `payment_id` on the transaction.

Error handling: if the stored Mollie id is no longer resolvable in
Mollie (deleted / archived), Mollie responds with an API exception.
The refund manager surfaces the error message and disables further
actions — no local fallback attempt (see Decisions, point 1).

`RefundGateway` (new, behind an interface) wraps both endpoints; a
`RefundEndpointResolver` implements the rules above. No code path
outside the gateway touches `OrderEndpoint` or `PaymentEndpoint`
directly for refund purposes.

### Mollie tab in the order detail

Instead of overriding `sw-order-detail-general`, the plugin adds a
**new dedicated tab** called **"Mollie"** to the Shopware order
detail view, placed as the **last** tab. All Mollie-specific UI —
refund manager, shipping, cancel-items, plus whatever we add later —
lives inside that tab. The order overview itself is **no longer
modified** by the plugin.

Benefits:

- A JS error in Mollie code only breaks the Mollie tab, not the
  whole order detail. Merchants can still see and edit the order.
- All Mollie-specific admin-api traffic only runs when the merchant
  actually opens the Mollie tab, so loading a regular order detail
  stays fast.
- Future features (eligibility checks, payment-method info, webhook
  history) have a clean home instead of being shoe-horned into the
  general tab.

**Tab visibility** is driven by the **current order transaction**:

- If the current (last) transaction's payment method handler is a
  Mollie handler → tab is shown.
- Otherwise (non-Mollie payment, even if an earlier cancelled
  transaction was Mollie) → tab is hidden.

This matters for the common case of an abandoned-and-retried payment:
customer picks Mollie, cancels, then completes with a bank transfer
— the order is not a Mollie order anymore and the tab must not show.

**Labels / snippets:** reuse the existing `mollie-payments.*` snippet
namespace. No new snippet keys unless strictly required.

Implementation sketch:

- Extend the `sw-order-detail` route with a sub-route
  `order.detail.mollie` rendering a Mollie shell component.
- Shell component hosts three cards (all three moved into the tab in
  this feature — see Decisions, point 6): **Refunds**, **Shipping**,
  **Cancel items**. Refund is the focus of this feature; shipping and
  cancel-items get their own internal section in the tab but no
  behavioural rewrite in this feature.
- Each card is a small, testable component with its own store /
  composable; they do not know about each other.
- Tab visibility check is a cheap composable that inspects the order's
  current transaction payment method handler identifier — no
  admin-api call needed.

### Refund UI — component decomposition

`mollie-refund-manager/index.js` is broken into focused pieces:

- `MollieRefundTab.vue` — shell on the Mollie order tab, loads the
  order summary and dispatches children.
- `MollieRefundSummaryCard.vue` — shows totals: paid, refunded,
  pending-refund, remaining. Pure presentation.
- `MollieRefundForm.vue` — line-item selection + shipping + adjustment
  + refund button. Emits a `RefundRequest` payload.
- `MollieRefundHistoryList.vue` — list of completed and pending
  refunds, with cancel-action per pending refund.
- `MollieRefundItemRow.vue` — single line-item row with
  refundable-quantity and promotion handling.
- `useRefundManager.ts` (composable) — owns the admin-api calls and
  client-side state (current totals, in-flight requests).
- `useRefundEligibility.ts` — derives whether refund is currently
  possible (ACL + payment status + remaining amount) from data the
  composable already holds, so the decision is not a separate
  server round-trip.

Goals: each component < 150 LOC, no `mollie-refund-manager/index.js`
remains, no direct component override of Shopware core views.

### Consolidated admin-api route

Today, opening an order fires at minimum four admin-api calls for the
refund UI alone (`refund-manager/data`, `refund/list`, `refund/total`,
sometimes a config call). Target:

**One** admin-api route, `POST /api/_action/mollie/order/refund-overview`,
returns everything the Mollie tab needs for refunds in a single call:

```json
{
  "endpoint": "payment" | "order",
  "mollieId": "tr_xxx" | "ord_xxx",
  "eligibility": {
    "possible": true,
    "reasons": []
  },
  "totals": {
    "paid": "100.00",
    "refunded": "25.00",
    "pendingRefund": "0.00",
    "remaining": "75.00",
    "currency": "EUR"
  },
  "history": [
    {
      "id": "re_xxx",
      "amount": "25.00",
      "status": "refunded",
      "createdAt": "2026-04-20T09:30:00Z",
      "reason": "Damaged item"
    }
  ],
  "lineItems": [
    { "id": "...", "label": "...", "refundable": 2, "refunded": 0 }
  ]
}
```

- Route runs server-side, makes a single Mollie call (`GET /payments/{id}`
  or `GET /orders/{id}?embed=refunds`) plus the local DAL reads
  (including the new `order_line_item.customFields.mollie_payments.refunds`
  for Payments-API refunds), and assembles the view model.
- Creation / cancellation remain separate routes
  (`POST .../refund`, `POST .../refund/cancel`) because they mutate.
- Legacy routes (`refund-manager/data`, `refund/list`, `refund/total`)
  are **removed** in the same release that ships the new UI. They
  are admin routes not consumed by any third-party integration; no
  deprecation alias is kept.
- If the Mollie call fails, the route still returns a partial
  response with `eligibility.possible = false` and an
  `eligibility.reasons` entry, so the UI can render the "Mollie
  unavailable" empty-state and keep all actions disabled.

### PHP — RefundManager refactor

- Move to `shopware/Component/Refund/`, namespace
  `Mollie\Shopware\Component\Refund`. Class is `final`,
  `declare(strict_types=1)`, no more `RefundManager` god-object.
- Split into:
  - `RefundService` — create / cancel via the gateway.
  - `RefundOverviewService` — assembles the view-model for the new
    overview route.
  - `RefundEligibility` — pure class that decides whether a refund is
    possible for a given order + payment status + user ACL.
  - `RefundGateway` — Mollie-side call (payments vs orders
    endpoint), thin wrapper around the SDK.
  - `RefundEndpointResolver` — picks Payments API vs Orders API from
    the order's Mollie ids.
  - `RefundRequest`, `RefundItem`, `RefundOverview` — DTOs / value
    objects for controller <-> service contract.
- `RefundCreditNoteService` keeps its current responsibility but is
  moved into the same component folder.

---

## Proposed component changes

### New (under `shopware/Component/Refund/`)

- `RefundGateway` (interface + Payment + Order implementations)
- `RefundEndpointResolver`
- `RefundOverviewService`
- `RefundEligibility`
- `RefundOverviewController` — one route, GET-style POST returning
  the combined payload above.
- Vue: `MollieRefundTab`, `MollieRefundSummaryCard`,
  `MollieRefundForm`, `MollieRefundHistoryList`,
  `MollieRefundItemRow`, `useRefundManager`, `useRefundEligibility`.

### To drop

- `src/Components/RefundManager/RefundManager.php` — behaviour spread
  into the new services.
- `src/Resources/app/administration/src/module/mollie-payments/components/mollie-refund-manager/` — replaced.
- `extension/sw-order/view/sw-order-detail-general/` override — the
  Mollie tab replaces it.
- Legacy routes (`refund-manager/data`, `refund/list`, `refund/total`)
  once the UI switches to the overview route.

### To keep (temporarily)

- `src/Service/Refund/RefundService.php` — the Orders-API path
  remains available through the new `RefundGateway` implementation
  for orders that still have `mollie_order_id`. The service itself
  is migrated under `shopware/Component/Refund/Gateway/OrderRefundGateway.php`
  and the old path deleted.

---

## Data model

Two sources of truth, depending on the endpoint used:

### Custom fields (read-only inputs to the resolver)

- `order.customFields.mollie_payments.order_id` — only on orders
  created via the old Orders-API flow. Presence triggers the
  Orders-API refund path.
- `order_transaction.customFields.mollie_payments.payment_id` — set
  by the new Payments-API checkout on the successful transaction.
  Presence triggers the Payments-API refund path.

No migration of these fields. They are already populated correctly
by the existing code paths.

### New: per-line-item refund tracking for Payments-API refunds

The Payments API only accepts an amount + description per refund —
it does **not** know about line items. To keep feature parity with
the current Orders-API refund UX (per-line-item refunds, remaining
refundable quantity, credit-note line generation), we persist the
plugin-side line-item breakdown on the order line item itself:

`order_line_item.customFields.mollie_payments.refunds` (JSON array):

```json
[
  {
    "refundId": "re_xxx",
    "quantity": 1,
    "amount": "10.00",
    "currency": "EUR",
    "createdAt": "2026-04-22T09:30:00Z",
    "status": "refunded"
  }
]
```

Write path: when a Payments-API refund is created, the plugin posts
one refund to Mollie with the aggregated amount, then writes one
entry into each affected line item's custom field. Status stays in
sync through the existing webhook handling (Mollie webhook → update
the entries' `status` from `queued` → `pending` → `refunded` /
`failed`).

Derived values computed from this aggregate:

- Already-refunded quantity per line item.
- Remaining refundable quantity per line item (`orderLineItem.quantity
  - sum(refunds[].quantity)` filtered to non-failed refunds).
- Credit-note source data — `RefundCreditNoteService` reads the
  same custom field and emits per-line-item credit notes for
  Payments-API refunds (same UX as today's Orders-API credit
  notes).

For Orders-API refunds this field stays empty; the line-item data
comes from Mollie's own order model and is not mirrored locally.

Deletion: when a Payments-API refund is cancelled, remove the
corresponding entries. When it fails, flip `status` to `failed` and
ignore it in the derived totals. Entries are never rewritten
in-place — always append new, remove old by refund id.

No new table and no backfill; the custom field is additive and
defaults to an empty array when missing.

### Refund history / totals

Already-refunded totals are **not** persisted locally beyond the
custom field above; the overview route combines the local custom
field (for Payments-API refunds) or the Mollie order refund list
(for Orders-API refunds) live and returns them to the UI.

---

## Phases / work packages

Each phase is an independent PR. Phases 1–3 are prerequisites; 4 and
5 can be sequenced flexibly.

### Phase 1 — Mollie tab in order detail (refund + shipping + cancel-items)

- Register the new `sw-order.detail.mollie` route / tab at the last
  position, labelled "Mollie".
- Move the existing refund, shipping and cancel-items UIs into the
  tab (temporarily wrapping the current monolithic components so
  nothing breaks functionally).
- Remove the override of `sw-order-detail-general` and any other
  order-overview extensions introduced by the plugin.
- Tab visibility driven by the current order transaction's payment
  handler — only shown for Mollie-paid transactions.
- Cypress: tab renders, JS error inside the tab does not kill the
  order-detail view.

### Phase 2 — Admin-api consolidation

- Add `RefundOverviewService` + `RefundOverviewController` with the
  single `POST /api/_action/mollie/order/refund-overview` route.
- Build the combined response (endpoint + eligibility + totals +
  history + line items) from local DAL reads + one Mollie call.
- Vue switches to the new route.

### Phase 3 — Vue refactor

- Split `mollie-refund-manager` into the small components listed
  above.
- Introduce `useRefundManager` / `useRefundEligibility` composables.
- Delete the old component.

### Phase 4 — Payments-API refund gateway

- `RefundGateway` interface + `PaymentRefundGateway` +
  `OrderRefundGateway`.
- `RefundEndpointResolver` driven by the order custom field
  `mollie_payments.order_id` vs. the transaction custom field
  `mollie_payments.payment_id`.
- Route create / cancel through the gateway.
- Introduce the new `order_line_item.customFields.mollie_payments.refunds`
  custom field; write entries from the Payments-API refund path,
  keep them in sync via the existing webhook handling.
- `RefundCreditNoteService` reads the new custom field when
  generating credit notes for Payments-API refunds.
- No data migration — existing legacy orders keep going through the
  Orders-API gateway based on their existing custom field.

### Phase 5 — Clean-up

- Remove `src/Components/RefundManager/`.
- Delete the legacy admin-api routes (`refund-manager/data`,
  `refund/list`, `refund/total`) outright — no deprecation window.
- Remove legacy Vue component folders.
- Remove remnants of the `sw-order-detail-general` override.

---

## Tests

- **Unit:**
  - `RefundEndpointResolver` — decides payment vs order endpoint
    from the custom fields.
  - `RefundEligibility` — truth table over ACL, payment status,
    remaining amount.
  - `RefundOverviewService` — assembles the correct view model from
    fake DAL + fake Mollie gateway.
  - Vue: component tests for each new Vue piece (Vitest — small,
    focussed).
- **Integration** (PHP, against the Mollie sandbox via `MolliePage`):
  - Create a Payments-API refund on a fresh Payments-API order.
  - Create an Orders-API refund on an order with a stored
    `mollie_order_id` fixture.
  - Cancel a pending refund.
  - Overview route returns correct totals for partially-refunded
    orders.
- **Behat:**
  - Admin user opens an order with Mollie payment, sees the Mollie
    tab, issues a partial refund, sees totals update.
  - Admin user on a legacy Orders-API order can still refund.
  - Mollie tab tolerates a Mollie API error gracefully (toast + no
    crash).
- **Cypress** (existing refund E2E) gets rewritten for the new tab
  layout; old selectors go away.

---

## Assumptions and risks

- **Mollie Orders API remains available** long enough for legacy
  orders. If Mollie deprecates it, we need a follow-up feature that
  captures a `mollie_payment_id` for legacy orders (e.g. by looking
  up the related payment on the existing Mollie order) so they can
  move to Payments-API refunds. Not in scope here.
- **Amount rounding parity:** Orders API refunds use line items;
  Payments API refunds only take an amount + description. For
  Payments-API refunds the plugin computes the exact aggregated
  amount from the selected line items on the PHP side (in
  `RefundService`) — the UI must not calculate this client-side to
  avoid drift. The per-line-item breakdown is then persisted on the
  new `order_line_item.customFields.mollie_payments.refunds` custom
  field.
- **ACL:** `mollie_payments.refund_manager` stays as the single key.
  It gates the refund button, not the tab. The tab is visible to
  any admin user who can see the order; they just cannot trigger
  refunds without the permission. Same pattern will apply to
  shipping / cancel-items buttons.
- **Credit-note generation:** `RefundCreditNoteService` fires for
  both refund paths. For Payments-API refunds it reads the line-item
  breakdown from the new custom field to produce the same
  per-line-item credit notes customers get today on Orders-API
  refunds.
- **Partial refunds via Mollie dashboard:** if a merchant triggers a
  refund directly in the Mollie dashboard instead of via the plugin,
  the Payments-API refund arrives via webhook without any
  plugin-side line-item breakdown. The overview route shows it under
  "history" as an amount-only refund; credit notes for such refunds
  cannot be generated automatically (same limitation as today). No
  special handling in this feature.

---

## Decisions

All open questions have been resolved. The answers drive the body
of this document.

1. **Unresolvable Mollie id** → block the refund UI. If a stored
   `mollie_order_id` is no longer valid in Mollie, the API call
   fails with an exception anyway; the UI surfaces the error and
   disables further actions. No local fallback to the payment id.
2. **Orders-API deprecation logs** → none. Merchants update on
   their own schedule and may refund a legacy order even a year
   later. No log noise around it.
3. **Overview route exposure** → admin-internal only. Not advertised
   as a stable third-party API. If a support ticket ever requests
   it, revisit.
4. **Legacy admin-api routes** → deleted in the same release as the
   Vue refactor. No deprecation window — they are admin routes, no
   third-party integration relies on them.
5. **Tab label / position / snippets** → label "Mollie", position
   last, reuse the existing `mollie-payments.*` snippet namespace.
   Keep it simple.
6. **Mollie tab scope** → move refund **and** shipping **and**
   cancel-items into the tab in this feature. We are done editing
   the order overview; everything Mollie-specific goes into the tab
   at once.
7. **API-down UX** → empty state + all actions disabled. No cached
   totals, no inconsistent partial view.
8. **Refund reason** → stays free text.
9. **Pending-refund polling** → no polling. Merchant can reload the
   page to see updated status. Removes the recurring expensive
   Mollie fetch.
10. **Shipping cost refund** → kept as it is today (separate
    refundable line in the form).
11. **Credit notes on Payments-API refunds** → we persist the
    plugin-side line-item breakdown on
    `order_line_item.customFields.mollie_payments.refunds` (new
    custom field, see Data model). `RefundCreditNoteService` reads
    that field and produces the same per-line-item credit notes
    customers get today on Orders-API refunds. UI stays the same.
12. **ACL granularity** → existing `mollie_payments.refund_manager`
    key stays as-is, and it gates the **refund button** only. The
    Mollie tab is visible without that permission (read-only view
    of refunds, shipping status, cancel-items state). Same scheme
    will apply to shipping / cancel-items buttons inside the tab.
13. **Very old orders without any Mollie id** → refund button is
    disabled with an explanatory tooltip. Same mechanism as any
    other ineligible order. No special UI path.

---

## References

- `src/Components/RefundManager/RefundManager.php` — current 526-LOC
  god-object to be dismantled.
- `src/Service/Refund/RefundService.php` — current Orders-API +
  Payments-API fallback path.
- `src/Resources/config/routes/admin-api/refund.xml` — current admin
  routes.
- `src/Controller/Api/Order/RefundControllerBase.php` — current
  controller.
- `src/Resources/app/administration/src/module/mollie-payments/components/mollie-refund-manager/index.js` —
  728-LOC Vue god-component.
- `src/Resources/app/administration/src/module/mollie-payments/extension/sw-order/view/sw-order-detail-general/index.js` —
  the override that causes blanket breakage on JS errors.
- `tests/PHPUnit/Components/RefundManager/`, `tests/Cypress/e2e/storefront/refund/` —
  existing coverage to be ported.
- `../packages/payment.md` — refactor progress tracking for the
  Payment package (new RefundGateway lives under
  `shopware/Component/Payment/Gateway/` or
  `shopware/Component/Refund/Gateway/`, TBD during Phase 4).
