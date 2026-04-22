# Feature: PayPal Express — Temporary Orders-API Bridge

**Status:** Plan — decisions recorded, ready for implementation.
**Owner:** Vitalij Mik
**Created:** 2026-04-22
**Last updated:** 2026-04-22

---

## Motivation

The refactor switches every payment method from the Mollie
**Orders API** (`/v2/orders`) to the Mollie **Payments API**
(`/v2/payments`). For **PayPal Express** this cannot be completed
today: the `authenticationId` that PayPal Express must forward to
Mollie is accepted only by the Orders API — it goes into the
`payment` sub-array of the `POST /v2/orders` payload. The
Payments API has no equivalent field yet, so the authentication
reference is silently dropped and the checkout fails on PayPal's
side.

PayPal Express has to keep using the Orders API **until Mollie
ships an equivalent field on the Payments API**. We need a way to
route **exactly this one handler** through the legacy code path
while every other handler migrates freely.

The proposal: introduce a marker interface
`OrdersApiAwareInterface` in
`shopware/Component/Payment/Handler/`. The `Pay` action
(`shopware/Component/Payment/Action/Pay.php`) picks the endpoint
based on the interface. PayPal Express is the only handler that
implements it for now. When Mollie adds the field to the Payments
API, the interface goes away with a single revert commit, and the
entire Orders-API implementation in the plugin can be deleted as
part of the general refactor.

## Out of scope

- Actually removing the Orders-API code. The whole point of the
  interface is to keep the Orders-API path alive for one handler
  until Mollie closes the feature gap. Removal is tracked as a
  follow-up (see "Removal plan").
- The broader Orders-API → Payments-API migration for every other
  handler. That work runs in parallel; this doc only describes the
  exception carve-out.
- POS payments. POS already runs on the Payments API exclusively,
  in both the legacy and the new code path — it is not affected
  by this feature.
- PayPal Express address / guest sync — tracked separately in
  [`express-checkout-address-sync.md`](express-checkout-address-sync.md).
- The missing Payments-API field itself (no known ETA from Mollie).

---

## Current state (2026-04-22)

### The PayPal Express handler

- `shopware/Component/Payment/Method/PayPalExpressPayment.php` —
  final, extends `AbstractMolliePaymentHandler`, implements
  `SubscriptionAwareInterface` and `RecurringAwareInterface`.
- `applyPaymentSpecificParameters()` copies `authenticationId`
  from the `RequestDataBag` into the `CreatePayment` struct
  (`$payment->setAuthenticationId($authenticationId)`).
- Technical name: `molliepayments_paypalexpress` (derived from
  `parent::getTechnicalName() . 'express'`).

### The action that drives the checkout

`shopware/Component/Payment/Action/Pay.php` is the new-namespace
entry point that replaces `src/Facade/MolliePaymentDoPay.php` for
the migrated handlers. It:

1. Loads the transaction data.
2. Calls `CreatePaymentBuilder::build()` to produce a
   `CreatePayment` struct — the payload for Mollie.
3. Dispatches `ModifyCreatePaymentPayloadEvent`.
4. Calls `MollieGateway::createPayment()` — **always the Payments
   API**.
5. Propagates the `authenticationId` onto the returned payment
   struct (lines 94–97), so subsequent code has it — but by that
   point the payload has already been sent to the Payments API,
   where the field is not honoured.

The builder and gateway are narrow today:

- `CreatePaymentBuilderInterface::build(TransactionDataStruct, AbstractMolliePaymentHandler, RequestDataBag, Context): CreatePayment`
  — produces a single shape that maps to the Payments-API payload.
- `MollieGatewayInterface::createPayment(...)` — hits
  `POST /v2/payments`. There is **no** `createOrder` counterpart
  in the new-namespace action path; the Orders API is only called
  from legacy code under `src/`.

### The marker-interface pattern in the new namespace

`shopware/Component/Payment/Handler/` already uses this pattern for
capability flags. Existing interfaces:

- `SubscriptionAwareInterface`
- `RecurringAwareInterface`
- `BankTransferAwareInterface`
- `ManualCaptureModeAwareInterface`
- `DeprecatedMethodAwareInterface`

Each is an empty interface with a short docblock. Handlers opt in
by implementing the interface; downstream code checks
`$handler instanceof SomeAwareInterface`. That is the pattern the
new `OrdersApiAwareInterface` slots into.

### Existing legacy fallback in Finalize

`shopware/Component/Payment/Action/Finalize.php` calls
`MollieGateway::getPaymentByTransactionId()`. Its implementation
in `shopware/Component/Mollie/Gateway/MollieGateway.php` already
includes a `repairLegacyTransaction()` branch (lines 274–309)
that:

- Reads `order.customFields.mollie_payments.order_id`.
- If present, loads the payment via
  `GET /v2/orders/{id}?embed=payments` and extracts the first
  embedded payment.
- Stores the resulting `Payment` extension on the transaction so
  the rest of Finalize works unchanged.

This means: an order created via the Orders API (Mollie id
starting with `ord_*`) can be finalised through the same
`Finalize` action as a `tr_*` payment, with no additional
branching required. No migration concerns for in-flight PayPal
Express orders during rollout / removal — the fallback already
covers them.

### Why the Payments API does not work for PayPal Express today

- PayPal Express needs `authenticationId` (the PayPal-side
  session reference) to be forwarded to Mollie.
- In the Orders API that goes into the `payment` sub-array of the
  `POST /v2/orders` payload — `payment.authenticationId`.
- The Payments API has no equivalent field. The flag
  `applyPaymentSpecificParameters()` sets on the
  `CreatePayment` struct is therefore ignored when the struct is
  serialised for `POST /v2/payments`.
- Any attempt to route PayPal Express through the Payments API
  results in a PayPal-side failure because the information is
  silently dropped.

---

## Target state

1. A new marker interface
   `shopware/Component/Payment/Handler/OrdersApiAwareInterface.php`
   signals "this handler still needs the Orders API".
2. `PayPalExpressPayment` is the **only** handler that implements
   it.
3. `CreatePaymentBuilder` is renamed to `PayloadBuilder` and gets
   two public methods:
   - `buildPayment(TransactionDataStruct, AbstractMolliePaymentHandler, RequestDataBag, Context): CreatePayment`
     — Payments-API payload (current behaviour, renamed).
   - `buildOrder(TransactionDataStruct, AbstractMolliePaymentHandler, RequestDataBag, Context): CreateOrder`
     — Orders-API payload, places PayPal-specific parameters
     (including `authenticationId`) into the `payment` sub-array
     of the Orders payload.
4. `MollieGatewayInterface` exposes a `createOrder()` counterpart
   to `createPayment()` in the new namespace (the legacy version
   under `src/` stays as the implementation target for now; the
   interface just surfaces it to the `Pay` action).
5. `Pay::execute()` picks the endpoint based on the interface:
   ```
   if ($paymentHandler instanceof OrdersApiAwareInterface) {
       $createOrderStruct = $this->payloadBuilder->buildOrder(...);
       $order = $this->mollieGateway->createOrder($createOrderStruct, ...);
       // persist as legacy: order.customFields.mollie_payments.order_id
   } else {
       $createPaymentStruct = $this->payloadBuilder->buildPayment(...);
       $payment = $this->mollieGateway->createPayment($createPaymentStruct, ...);
       // persist as new: order_transaction.customFields.mollie_payments.payment_id
   }
   ```
6. The PayPal-specific `authenticationId` propagation at Pay.php
   lines 94–97 moves into `buildOrder()` — it is only relevant on
   the Orders-API path now.
7. Finalize stays untouched. The existing
   `repairLegacyTransaction()` fallback handles `ord_*`
   transactions already, including those created by PayPal Express
   after this change lands.
8. When Mollie ships the missing field on the Payments API, the
   interface, its implementation on `PayPalExpressPayment`, the
   `buildOrder()` method and the `Pay`-action branch are removed
   in one PR. The whole Orders-API code path
   (`MollieGateway::createOrder`, `repairLegacyTransaction` once
   no `ord_*` orders remain, related DTOs) can then be deleted as
   part of the general refactor.

---

## Proposed component changes

### New

- `shopware/Component/Payment/Handler/OrdersApiAwareInterface.php`
  — empty marker interface, docblock explains it is a temporary
  bridge and links this document.

### To adjust

- **`shopware/Component/Payment/CreatePaymentBuilder.php` /
  `CreatePaymentBuilderInterface.php`** → renamed to
  `PayloadBuilder` / `PayloadBuilderInterface`. `build()` becomes
  `buildPayment()`; new `buildOrder()` method added.
- **`shopware/Component/Payment/Method/PayPalExpressPayment.php`**
  implements `OrdersApiAwareInterface` in addition to its current
  marker interfaces. `applyPaymentSpecificParameters()` keeps
  setting `authenticationId` on the struct; the payload serialiser
  for Orders moves it into the `payment` sub-array.
- **`shopware/Component/Payment/Action/Pay.php`** routes based on
  `$paymentHandler instanceof OrdersApiAwareInterface`. The
  PayPal-specific `authenticationId` block at lines 94–97 is
  removed — it is no longer needed because the correct placement
  happens inside `buildOrder()`.
- **`shopware/Component/Mollie/Gateway/MollieGatewayInterface.php`**
  gains `createOrder(...)` alongside `createPayment(...)`.
  Implementation reuses the legacy Orders-API client call.

### To drop (later, not as part of this feature)

- `OrdersApiAwareInterface` itself.
- Its implementation on `PayPalExpressPayment`.
- `PayloadBuilder::buildOrder()` and the `createOrder()` branch in
  `Pay::execute()`.
- `MollieGateway::createOrder()` and
  `repairLegacyTransaction()` once no Orders-API-created orders
  remain (the latter can stay indefinitely for historic orders
  — it is cheap).

---

## Phases / work packages

Each phase is an independent PR.

### Phase 1 — Rename `CreatePaymentBuilder` → `PayloadBuilder`

- Rename the class, interface, file and service reference.
- Rename `build()` → `buildPayment()` (no behaviour change).
- Update the one caller (`Pay::execute()` line 79) and the
  Autowire attribute on line 40.
- Pure refactor, no functional change. Existing tests adapted to
  the new names.

### Phase 2 — Introduce the `OrdersApiAwareInterface`

- Add `OrdersApiAwareInterface` under
  `shopware/Component/Payment/Handler/`.
- `PayPalExpressPayment` implements it.
- Unit test: `PayPalExpressPayment` is an instance of
  `OrdersApiAwareInterface`, and no other handler under
  `shopware/Component/Payment/Method/*` is.

### Phase 3 — `PayloadBuilder::buildOrder()` and gateway
`createOrder()`

- Add `buildOrder()` to `PayloadBuilderInterface` /
  `PayloadBuilder`. It produces the Orders-API payload (shape
  matches `src/Service/MollieApi/OrderBuilder::buildOrderPayload`;
  port only what the new-namespace handlers need).
- `authenticationId` is placed into the `payment` sub-array of
  the Orders payload when the handler exposes it on the struct.
- Add `createOrder(...)` to `MollieGatewayInterface`.
  Implementation delegates to the legacy Orders-API client path.
- Unit tests: builder produces the expected payload shape for a
  PayPal Express handler, including
  `payment.authenticationId`; gateway's `createOrder` issues the
  right HTTP call (covered by a fake HTTP client or the existing
  Mollie sandbox in integration).

### Phase 4 — Switch `Pay::execute()` on the interface

- `Pay::execute()` branches on
  `$paymentHandler instanceof OrdersApiAwareInterface`:
  - **true** → `buildOrder()` + `createOrder()`; persist
    `order.customFields.mollie_payments.order_id`.
  - **false** → existing `buildPayment()` + `createPayment()`
    path unchanged.
- Remove the PayPal-specific `authenticationId` block at Pay.php
  lines 94–97.
- Behat: an end-to-end PayPal Express checkout against the
  Mollie sandbox succeeds and writes the Mollie id with the
  `ord_` prefix into
  `order.customFields.mollie_payments.order_id` — **not** into
  `order_transaction.customFields.mollie_payments.payment_id`.
- Behat: a non-PayPal-Express Mollie-method checkout (e.g.
  iDEAL) continues to write `tr_*` into
  `order_transaction.customFields.mollie_payments.payment_id`.
- Finalize is re-verified: a `ord_*` PayPal Express transaction
  finishes via the existing `repairLegacyTransaction()` path.

### Phase 5 — Removal (deferred, external trigger)

Triggered by Mollie shipping the missing Payments-API field.
When that happens:

- Teach `PayPalExpressPayment::applyPaymentSpecificParameters()`
  to forward the new Payments-API field. Drop the `payment`
  sub-array placement.
- Drop `OrdersApiAwareInterface` and remove the
  `instanceof OrdersApiAwareInterface` branch in `Pay::execute()`.
- Drop `PayloadBuilder::buildOrder()`.
- Drop `MollieGateway::createOrder()`.
- Coordinate with `refunds.md` — the refund feature keeps an
  independent Orders-API fallback for legacy refunds via stored
  `order.customFields.mollie_payments.order_id`. That fallback
  lives on as long as historic orders exist with only the old
  custom field; it is **not** coupled to this interface.

---

## Tests

- **Unit** (new, under `tests/Unit/Payment/Handler/`):
  - `OrdersApiAwareInterfaceTest` — asserts the interface exists
    and is empty (guards against accidental method additions).
  - `PayPalExpressPaymentTest` — asserts the handler implements
    `OrdersApiAwareInterface` alongside
    `SubscriptionAwareInterface` and `RecurringAwareInterface`
    (regression guard against losing any of them).
- **Unit** (new, under `tests/Unit/Payment/`):
  - `PayloadBuilderTest` — produces expected payload shape for
    `buildOrder()`; specifically asserts that for a handler that
    sets `authenticationId`, the result carries it at
    `payment.authenticationId`.
  - `PayTest` — faked handler implementing
    `OrdersApiAwareInterface` reaches the `createOrder` call on
    a gateway fake; faked handler without it reaches
    `createPayment`.
- **Integration / Behat**:
  - PayPal Express full checkout against the Mollie sandbox via
    `MolliePage` still completes successfully after the switch.
  - Fingerprint assertion: the order row carries
    `customFields.mollie_payments.order_id` starting with `ord_`;
    the transaction row does **not** carry
    `customFields.mollie_payments.payment_id`.
  - Smoke test for at least one Payments-API handler (e.g.
    iDEAL) in the same run — the fingerprint is inverted:
    `payment_id` starting with `tr_` on the transaction, no
    `order_id` on the order.
  - Finalize against an `ord_*` Mollie id resolves via
    `repairLegacyTransaction()` without additional branching in
    the action.

---

## Decisions

All open questions have been resolved.

1. **Missing Payments-API field** → confirmed:
   `authenticationId`. In the Orders API it lives under the
   `payment` sub-array of the `POST /v2/orders` payload
   (`payment.authenticationId`). No equivalent on `POST /v2/payments`
   today. When Mollie ships an equivalent, Phase 5 removes the
   whole carve-out.
2. **Interface name** → `OrdersApiAwareInterface`. Consistent
   with the other empty markers in
   `shopware/Component/Payment/Handler/`
   (`SubscriptionAwareInterface`, `RecurringAwareInterface`, …).
3. **Where to branch** → in
   `shopware/Component/Payment/Action/Pay.php`, not in the
   legacy `src/Facade/MolliePaymentDoPay.php`. The builder is
   renamed `CreatePaymentBuilder` → `PayloadBuilder` and gains a
   `buildOrder()` method alongside `buildPayment()`. `Pay` picks
   the builder method and gateway call based on the interface;
   the legacy facade is not touched.
4. **POS** → ignored. POS has always run on the Payments API,
   even in the legacy code, and is not affected by this carve-out.
5. **In-flight orders during rollout / removal** → already
   handled by the existing `repairLegacyTransaction()` fallback
   in `MollieGateway::getPaymentByTransactionId()` (lines
   274–309). An order created with an `ord_*` id finalises
   correctly without additional branching. No migration needed.

---

## References

- `shopware/Component/Payment/Action/Pay.php` — target action for
  the endpoint switch; current Payments-API-only path.
- `shopware/Component/Payment/Action/Finalize.php` — unchanged;
  relies on the existing legacy fallback in the gateway.
- `shopware/Component/Mollie/Gateway/MollieGateway.php`
  (`repairLegacyTransaction`, lines 274–309;
  `getPaymentByMollieOrderId`, lines 249–272) — legacy fallback
  that makes `ord_*` finalisation transparent.
- `shopware/Component/Payment/CreatePaymentBuilder.php` /
  `CreatePaymentBuilderInterface.php` — to be renamed to
  `PayloadBuilder` / `PayloadBuilderInterface` with
  `buildPayment` / `buildOrder`.
- `shopware/Component/Payment/Method/PayPalExpressPayment.php` —
  target handler for the new marker.
- `shopware/Component/Payment/Handler/SubscriptionAwareInterface.php`,
  `RecurringAwareInterface.php`,
  `BankTransferAwareInterface.php`,
  `ManualCaptureModeAwareInterface.php`,
  `DeprecatedMethodAwareInterface.php` — existing empty-marker
  pattern to follow.
- `src/Facade/MolliePaymentDoPay.php::createMollieOrder()`
  (lines 375–430) — legacy reference for the Orders-API payload
  shape that `PayloadBuilder::buildOrder()` has to reproduce.
- [`refunds.md`](refunds.md) — independent Orders-API fallback
  path for legacy refunds via stored
  `order.customFields.mollie_payments.order_id`.
- [`express-checkout-address-sync.md`](express-checkout-address-sync.md)
  — separate PayPal Express work package.
- Mollie docs:
  - Payments API: https://docs.mollie.com/reference/v2/payments-api
  - Orders API: https://docs.mollie.com/reference/v2/orders-api
