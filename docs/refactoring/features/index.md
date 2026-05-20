# Release Features

Product-level changes that need to land as part of the `src/` → `shopware/`
refactor. These run **in parallel** to the test-coverage work — coverage is
about refactoring-safety, features here are customer-visible behaviour that
the release needs.

Each file is a rough plan, not a finished spec: status quo, target state,
phases, and open questions a second developer can pick up from.

## Features

- [Multi-Subscription Checkout](subscriptions.md) — mixed carts, multiple
  subscriptions per order, per-interval renewal orders, price-update
  workflow. **Status:** Plan — decisions recorded, ready for
  implementation.
- [Refunds on Payments API](refunds.md) — switch refund gateway to
  Payments API, Orders-API fallback for legacy orders via stored
  Mollie ids, separate Mollie tab in order detail, Vue decomposition,
  consolidated overview route. **Status:** Plan — decisions
  recorded, ready for implementation.
- [Express Checkout — Address & Guest Account Sync](express-checkout-address-sync.md)
  — complete the PayPal Express address-reuse path, unify logic in
  a shared `AddressSynchronizer`, extend the match hash with phone
  and company, add missing tests. **Status:** Plan — decisions
  recorded, ready for implementation.
- [PayPal Express — Temporary Orders-API Bridge](paypal-express-orders-api.md)
  — carve PayPal Express out of the Payments-API migration via a
  new `OrdersApiAwareInterface` marker and a
  `CreatePaymentBuilder` → `PayloadBuilder` rename with
  `buildPayment` / `buildOrder`. Revert in one step when Mollie
  ships the missing `authenticationId` field on the Payments API.
  **Status:** Plan — decisions recorded, ready for implementation.
- [Payment Links for Admin-Created Orders](payment-links.md) —
  use Mollie's Payment Links API so customers can pay
  admin-created orders directly from the confirmation email
  without logging into the shop. Triggered off
  `createdById !== null` + `AdminApiSource`; delete + recreate
  on order-total changes; shared webhook endpoint with `pl_*`
  fallback; gateway stays on Guzzle (no SDK). **Status:** Plan —
  decisions recorded, implementation deferred.

## Conventions

- One file per feature.
- Keep the "Open questions" section up to date as answers come in — strike
  through or move resolved ones into the body.
- Link back from the relevant `../packages/*.md` file(s) so the per-package
  tracking stays discoverable.
