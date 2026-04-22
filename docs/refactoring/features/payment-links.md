# Feature: Payment Links for Admin-Created Orders

**Status:** Plan — decisions recorded, implementation deferred.
**Owner:** Vitalij Mik
**Created:** 2026-04-22
**Last updated:** 2026-04-22

---

## Motivation

When a merchant creates an order for a customer from the Shopware
admin backend, the current flow forces the customer through
several manual steps to pay:

1. Shopware sends the customer an order-confirmation / payment
   email.
2. The customer logs into the shop account.
3. The customer navigates to the order in their account area.
4. The customer opens the "edit order" flow and pays from there.

Every step is a drop-off point, and the login step in particular
is a hard blocker for customers who do not remember their
credentials or do not have an account in the first place.

**Mollie's Payment Links API** (`POST /v2/payment-links`,
<https://docs.mollie.com/reference/create-payment-link>) solves
exactly this: it returns a shareable URL tied to a fixed amount.
The customer clicks the URL, completes the payment in Mollie's
hosted checkout, and the result is reported back via webhook. No
shop login required.

The feature is **for later** — it is written down now so the plan
doesn't get lost. Implementation is deferred until the
higher-priority items (Payments-API switch, subscriptions, refunds,
express checkout) are through.

## Out of scope

- Payment Links for storefront-placed orders. Storefront orders
  already flow through the normal Mollie payment process; adding
  a link would duplicate the payment.
- Payment Links for reminder / dunning mails (later feature).
  This doc only covers the initial admin-created order email.
- Merchant-facing UI to create standalone payment links outside
  of an order (also a later feature).
- Multi-payment links (Mollie supports one payment link
  completing multiple payments — not needed here).
- Refund handling via the Payment Links endpoint — refunds are
  already covered by [`refunds.md`](refunds.md) and operate on
  the underlying `tr_*` payment, not on the `pl_*` link.
- Shipping a plugin-owned mail template, a twig extension, or a
  Flow Builder storer that surfaces the link URL. The
  merchant-side mail template is adjusted manually against the
  merchant-docs recipe (see "Mail template" under Decisions,
  point 4). Less code, fewer moving parts.
- Async link creation via the Shopware message queue. Synchronous
  `order.written` handling is the chosen approach; see
  Decisions, point 7.

---

## Current state (2026-04-22)

### Admin order creation today

- Shopware's admin backend creates orders through the Admin API.
  The resulting `order` row has `createdById` set to the admin
  user's id. A storefront order has `createdById === null`.
- The Admin API context carries an
  `\Shopware\Core\Framework\Api\Context\AdminApiSource` as its
  source. Storefront calls carry `SalesChannelApiSource`. The
  plugin already uses this distinction — e.g.
  `src/Subscriber/CancelOrderSubscriber.php` guards with
  `!($context->getSource() instanceof SalesChannelApiSource)`.
- No subscriber in this plugin currently reacts to admin order
  creation. The plugin's existing `CheckoutOrderPlacedEvent`
  consumer is storefront-only (fired from
  `src/Components/Subscription/Actions/RenewAction.php` for
  subscription renewals; the `FlowBuilderEventFactory` wraps it
  but is not used for admin-created orders).
- The merchant currently relies on Shopware's built-in "order
  confirmation" mail (or a Flow Builder flow that sends it). The
  plugin ships no custom mail templates.

### Mollie Gateway surface

- `shopware/Component/Mollie/Gateway/MollieGateway.php` currently
  exposes `createPayment()`, `refund()`, `getPaymentByTransactionId`,
  and `repairLegacyTransaction` (Orders-API fallback).
- The gateway talks to Mollie **directly via Guzzle**, not through
  the bundled SDK. `ClientFactory::create(...)` returns a
  `GuzzleHttp\Client` pre-configured with base URL and auth, and
  the gateway does raw REST calls such as
  `$client->post('payments', ['form_params' => $molliePayment->toArray()])`
  (line 97) and `$client->get('profiles/me')` (line 119).
  Responses are hydrated into plugin-owned DTOs
  (`Payment::createFromClientResponse($body)`).
- The vendored SDK (`vendor_manual/mollie-api-php`) is still
  referenced in a few legacy spots under `src/` but is scheduled
  for removal as part of the ongoing refactor. New gateway
  methods must not introduce new SDK usages — stay on the Guzzle
  path so the SDK can be pulled out without touching this code.
- No `createPaymentLink()` / `deletePaymentLink()` /
  `getPaymentLinkById()` methods yet.
- ID-prefix-based dispatching exists for `ord_*` vs `tr_*`;
  `pl_*` will be a new third prefix.

### Custom-field pattern

- Struct: `src/Struct/Order/OrderAttributes.php` — order-level
  Mollie custom fields (`mollieOrderId`, `molliePaymentId`,
  `molliePaymentUrl`, `transactionReturnUrl`, `thirdPartyPaymentId`,
  …). Reads/writes `order.customFields['mollie_payments'][key]`.
- Transaction-level custom fields use the same `mollie_payments`
  key on `order_transaction.customFields` for
  `payment_id` (new, `tr_*`) — the appropriate location for the
  new `payment_link_id` / `payment_link_url` because the link
  belongs to a specific payment attempt, not to the order as a
  whole.
- Writer: `src/Service/UpdateOrderCustomFields.php` (order scope)
  and the transaction-scoped counterpart used in
  `src/Facade/MolliePaymentDoPay.php`.

### Webhook route

- `shopware/Component/Payment/Route/WebhookRoute.php` —
  `/api/mollie/webhook/{transactionId}` keyed by **Shopware**
  transaction id. The route looks up the Mollie payment via
  `MollieGateway::getPaymentByTransactionId()`, which reads the
  `mollie_payments` custom fields on the Shopware transaction.
- Dispatches `WebhookEvent` + status-specific events (paid /
  failed / expired / …) based on the status of the looked-up
  payment.
- Today the custom field always contains a `tr_*` id. For
  Payment Links it will initially contain only a `pl_*` id;
  the webhook handler has to resolve that to the latest payment
  attached to the link before it can reuse the existing
  state-update code. See Decisions, point 6.

---

## Target state

1. When an admin user creates an order in the backend **and** the
   plugin setting for payment-link generation is enabled on that
   sales channel, the plugin creates a Mollie Payment Link for
   that order and stores the link id + url on the order
   transaction (`order_transaction.customFields.mollie_payments.payment_link_id`,
   `…payment_link_url`).
2. The transactional email the merchant sends uses the stored
   URL directly. The merchant adjusts their mail template against
   the recipe in the plugin wiki — no plugin-side twig extension,
   Flow Builder storer, or shipped template.
3. When the admin edits the order and the total changes while the
   link is still active, the plugin deletes the old link via
   `DELETE /v2/payment-links/{id}` and creates a fresh one for
   the new total. The transaction custom fields are rewritten.
4. When the customer pays via the link, Mollie calls the
   webhook URL registered on the link. The webhook handler
   looks up the latest payment attached to the `pl_*`, writes
   its `tr_*` into the existing
   `mollie_payments.payment_id` custom field, and continues
   through the existing state-update code.
5. Shopware's order transitions to "paid" exactly as it would
   from a normal Mollie checkout. The merchant does not need to
   trigger anything.
6. If the customer never uses the link, it expires at the
   merchant-configured `expiresAt` and the order stays in its
   initial state. If the customer instead pays via the
   edit-order flow in the shop, Shopware creates a new
   transaction and the original link simply goes unused — no
   active cleanup required (see Decisions, point 8).

---

## Proposed component changes

### New

- `shopware/Component/Mollie/Gateway/MollieGatewayInterface.php`
  gains:
  - `createPaymentLink(CreatePaymentLink $payload, string $salesChannelId): PaymentLink`
  - `deletePaymentLink(string $paymentLinkId, string $salesChannelId): void`
  - `getPaymentLinkById(string $paymentLinkId, string $salesChannelId): PaymentLink`
    (needed by the webhook resolver — returns the link plus its
    latest payment id).

  Implementations follow the existing Guzzle pattern of
  `createPayment()`:
  `$client->post('payment-links', ['form_params' => $payload->toArray()])`,
  `$client->delete('payment-links/' . $id)`,
  `$client->get('payment-links/' . $id . '?embed=payments')`.
  Responses hydrated via `PaymentLink::createFromClientResponse($body)`.
  No SDK usage.

- `shopware/Component/Mollie/CreatePaymentLink.php` — request
  DTO. `toArray()` returns the shape for `POST /v2/payment-links`
  (`amount`, `description`, `redirectUrl`, `webhookUrl`,
  `expiresAt`, `metadata`).
- `shopware/Component/Mollie/PaymentLink.php` — response DTO with
  a static `createFromClientResponse(array $body): self`
  factory, mirroring `Payment::createFromClientResponse`.
  Exposes `id` (`pl_*`), `paymentUrl`, `expiresAt`, `status`,
  and (when `embed=payments` was used) `latestPaymentId` (`tr_*`).
- `shopware/Component/Payment/Action/CreateAdminOrderPaymentLink.php`
  — orchestrator for a given `OrderEntity`: builds the DTO from
  order totals and plugin settings, calls the gateway, persists
  the result to the transaction custom fields.
- `shopware/Component/Payment/Action/DeleteAdminOrderPaymentLink.php`
  — deletes an existing link via the gateway and clears the
  custom fields on the transaction.
- `shopware/Subscriber/AdminOrderPaymentLinkSubscriber.php`
  listens to `order.written` and branches on the write operation:
  - `OPERATION_INSERT` + admin signal (see below) + setting
    enabled → `CreateAdminOrderPaymentLink`.
  - `OPERATION_UPDATE` + admin signal + existing `payment_link_id`
    on the transaction + total changed →
    `DeleteAdminOrderPaymentLink` followed by
    `CreateAdminOrderPaymentLink`.

  Admin signal: `OrderEntity::getCreatedById() !== null`
  **AND** `$context->getSource() instanceof AdminApiSource`.
  Both required (Decisions, point 5).
- New plugin configuration section **"Payment Links"** with:
  - `enabled` (bool, default **off**, per sales channel).
  - `linkLifetimeDays` (int, default `14`, configurable —
    translates to `expiresAt = now + N days` when creating the
    link).

### To adjust

- `src/Struct/Order/OrderAttributes.php` (or a new equivalent in
  `shopware/Struct/Order/OrderAttributes.php` once the struct
  migrates) — add `paymentLinkId` / `paymentLinkUrl` fields.
  Canonical home is the **transaction** custom fields; order-level
  storage is not needed.
- `shopware/Component/Payment/Route/WebhookRoute.php` /
  `shopware/Component/Mollie/Gateway/MollieGateway.php::getPaymentByTransactionId()`
  — shared endpoint with a `pl_*` fallback. When the transaction
  custom fields only contain a `payment_link_id` (and no
  `payment_id`), the gateway resolves the link to its latest
  payment (`GET /v2/payment-links/{id}?embed=payments`), writes
  the resulting `tr_*` into the transaction custom fields, and
  continues through the normal payment lookup. One route, two id
  types. See Decisions, point 6.

### To drop

Nothing.

---

## Phases / work packages

Each phase is an independent PR.

### Phase 1 — Gateway + DTOs for Payment Links

- Add `CreatePaymentLink` / `PaymentLink` DTOs.
- Add `MollieGateway::createPaymentLink()`,
  `deletePaymentLink()`, `getPaymentLinkById()`.
- Implementation stays on the Guzzle path
  (`$client->post('payment-links', …)`,
  `$client->delete('payment-links/{id}')`,
  `$client->get('payment-links/{id}?embed=payments')`); no
  `$client->paymentLinks` (SDK) usage. Matches the existing
  `createPayment()` pattern and keeps the SDK removal clean.
- Unit test: DTOs emit / hydrate the expected payload shapes;
  gateway posts / deletes / gets against a faked HTTP client.
- Integration test: create a link against the Mollie sandbox,
  confirm id prefix `pl_` and a non-empty `paymentUrl`; delete
  it, confirm a subsequent `GET` returns 410/not-found.

### Phase 2 — Subscribe to admin order creation

- `AdminOrderPaymentLinkSubscriber` on `order.written`.
- Filter: `EntityWriteResult::getOperation() === OPERATION_INSERT`
  AND the loaded `OrderEntity::getCreatedById() !== null`
  AND `$context->getSource() instanceof AdminApiSource`.
- For each match, invoke `CreateAdminOrderPaymentLink`.
- Persist `payment_link_id` / `payment_link_url` on the
  `order_transaction` via the existing custom-field writer.
- Plugin configuration: new **Payment Links** section with
  `enabled` (default off) and `linkLifetimeDays` (default 14).
- Synchronous execution — accepted trade-off documented in
  Decisions, point 7.
- Unit tests:
  - Admin-context + `createdById` set + setting on → orchestrator
    called.
  - Admin-context + `createdById` null → no call.
  - Storefront-context (`SalesChannelApiSource`) → no call.
  - Setting off → no call.
  - Operation != insert → no call.
- Integration test: admin-created fixture order → link
  persisted on transaction with prefix `pl_`; storefront order
  → no link.

### Phase 3 — Handle admin edits after link creation

- Subscriber extends to `OPERATION_UPDATE`. Triggered when:
  - Same admin signals (`createdById` set +
    `AdminApiSource`).
  - A `payment_link_id` already exists on the transaction.
  - The order total (`amountTotal`) after the update differs
    from before. (Detect via the `OrderEvents::ORDER_WRITTEN_EVENT`
    payload's changed fields, or compare against the current DB
    row before writing the new link.)
- Action: `DeleteAdminOrderPaymentLink` → clears custom fields →
  `CreateAdminOrderPaymentLink` → writes the new link.
- Mollie `DELETE /v2/payment-links/{id}` returns 204 on success.
  If it returns 422 (link already paid), the order is effectively
  paid already and this branch should never fire — guard by
  checking the link status before attempting delete.
- Unit tests:
  - Update with unchanged total → no call.
  - Update with changed total + existing link → delete + create.
  - Update on an order that had no link → no call (not in scope).
- Integration test: admin-created order with total 100 → link
  created → admin edits to total 120 → old link is 404 on Mollie,
  new link on transaction has the 120 amount.

### Phase 4 — Webhook for `pl_*` on the shared endpoint

- Keep the single endpoint
  `/api/mollie/webhook/{transactionId}`. No new route.
- In `MollieGateway::getPaymentByTransactionId()`, when the
  transaction custom fields contain a `payment_link_id` but no
  `payment_id`:
  1. Call `getPaymentLinkById($plId, …)` with `embed=payments`.
  2. Take the latest payment's `tr_*` from the embed.
  3. Write `payment_id = tr_*` into the transaction custom
     fields via the existing custom-field writer.
  4. Continue through the existing payment-status flow unchanged.
- No `pl_*` webhook event types are needed — once the `tr_*`
  is resolved, the existing
  `WebhookStatusPaidEvent` / `WebhookStatusFailedEvent` / …
  chain fires as always.
- Behat: a scripted admin-order flow creates an order, customer
  completes the link against the sandbox, Shopware transaction
  transitions to Paid. No shop login at any step.

### Phase 5 — Polish

- Plugin configuration UI for the Payment Links section
  (wired up in Phase 2, visually finalised here).
- Plugin-wiki recipe for the mail-template edit (copy-pasteable
  twig snippet that reads `transaction.customFields.mollie_payments.payment_link_url`).
- Basic logging around link creation / deletion / resolution
  for support debugging.

---

## Tests

- **Unit** (`tests/Unit/Mollie/`):
  - `CreatePaymentLinkTest` — DTO serialises to the expected
    Mollie payload (amount, description, redirectUrl, webhookUrl,
    expiresAt, metadata).
  - `PaymentLinkTest` — DTO hydrates from a captured Mollie
    response, including the `embed=payments` variant.
- **Unit** (`tests/Unit/Subscriber/`):
  - `AdminOrderPaymentLinkSubscriberTest` with fakes for every
    admin-signal + operation + setting combination listed in
    Phases 2 + 3.
- **Integration**
  (`tests/Integration/Mollie/PaymentLinkGatewayTest.php`,
  `IntegrationTestBehaviour`):
  - Create / delete / get-with-embed against the Mollie sandbox;
    assert id prefix `pl_` and delete returns 204.
- **Integration**
  (`tests/Integration/Payment/AdminOrderPaymentLinkTest.php`):
  - Admin-created fixture order → link persisted on transaction.
  - Order total change → old link deleted, new link on
    transaction.
  - Webhook resolver: transaction with only `payment_link_id` →
    `getPaymentByTransactionId()` writes the `tr_*` into custom
    fields and returns the payment struct.
- **Behat** (`tests/Behat/Features/AdminOrderPaymentLink.feature`):
  - Full admin-order flow: create order with admin, open the
    stored `payment_link_url`, complete payment in the Mollie
    sandbox, Shopware transaction transitions to Paid.
  - No shop login at any step.

---

## Decisions

All open questions have been resolved.

1. **Activation granularity** → plugin setting, default **off**.
   Configurable per sales channel (standard Shopware plugin-config
   mechanism). Merchants opt in explicitly so no live links get
   sent before the merchant has updated the mail template.
2. **Default link lifetime** → configurable via a new plugin
   configuration section **"Payment Links"**. Default value
   `linkLifetimeDays = 14`. Mollie's `expiresAt` is set to
   `now + linkLifetimeDays` at creation time. Merchants with
   stricter requirements can shorten it.
3. **Admin edits the order after link creation** → delete the
   old link via `DELETE /v2/payment-links/{id}` and create a new
   one for the updated total. Only triggered when the order
   total actually changes. If the delete fails because the link
   was already paid, the order is effectively paid already and
   the branch shouldn't fire — guard on link status.
4. **Mail template strategy** → **option 1 only**: document the
   variable in the plugin wiki and ask the merchant to adjust
   their own mail template. No twig extension, no Flow Builder
   storer, no shipped alternative template. The reasoning: too
   much code inside the plugin for something that merchants
   customise anyway increases the bug surface. The stored URL is
   already reachable from any Flow Builder mail action via
   `transaction.customFields.mollie_payments.payment_link_url`.
5. **Admin-order signal** → combination of
   `OrderEntity::getCreatedById() !== null` **AND**
   `$context->getSource() instanceof AdminApiSource`. Either
   alone could produce false positives (integration tokens,
   seed / import code running under admin context with
   `createdById` unset, or vice versa). Requiring both filters
   down to actual admin-UI-driven order creation.
6. **Webhook endpoint** → single shared endpoint
   `/api/mollie/webhook/{transactionId}` handles both existing
   `tr_*` transactions and new `pl_*` ones. The fallback lives
   inside `MollieGateway::getPaymentByTransactionId()`: when a
   transaction only has `payment_link_id` in its custom fields,
   the gateway resolves the link's latest payment, writes the
   resulting `tr_*` into the transaction custom fields, and
   continues through the existing state-update code. No new
   route, no `pl_*`-specific events.
7. **Sync vs. async link creation** → **synchronous** for now.
   The message-queue approach adds complexity without clear
   benefit at this stage. The `order.written` DAL event fires
   post-commit, so a Mollie-side HTTP failure leaves the order
   persisted without a payment link — degraded but not broken.
   A manual "regenerate link" admin action can be added later
   if this becomes an operational issue.
8. **Double payment** → not handled specially. If the customer
   pays via the shop's edit-order flow instead of the link,
   Shopware creates a new transaction; the original link simply
   goes unused until `expiresAt`. No active cleanup. Conversely,
   once the link is paid, the transaction moves to Paid and any
   further in-shop payment attempt is rejected by the normal
   Shopware transaction state machine.

---

## References

- Mollie docs — <https://docs.mollie.com/reference/create-payment-link>
- Mollie docs — <https://docs.mollie.com/reference/delete-payment-link>
- Mollie docs — <https://docs.mollie.com/reference/get-payment-link>
- `shopware/Component/Mollie/Gateway/MollieGateway.php` — target
  class for the new `createPaymentLink()` / `deletePaymentLink()` /
  `getPaymentLinkById()`. Matches the existing Guzzle pattern
  used by `createPayment()` (line 97:
  `$client->post('payments', ['form_params' => …])`). The
  bundled SDK (`vendor_manual/mollie-api-php`) is not used for
  new gateway methods — it is scheduled for removal.
- `shopware/Component/Mollie/Gateway/ClientFactoryInterface.php`
  — returns a `GuzzleHttp\Client`, pre-configured with Mollie
  base URL and auth.
- `shopware/Component/Payment/Route/WebhookRoute.php` —
  single shared webhook endpoint; the `pl_*` fallback lives in
  the gateway (`getPaymentByTransactionId`), not in the route.
- `src/Struct/Order/OrderAttributes.php` — pattern for reading /
  writing Mollie custom fields. New fields
  `payment_link_id` / `payment_link_url` live on the
  **transaction** custom fields, not on the order.
- `src/Subscriber/CancelOrderSubscriber.php` — existing reference
  for the `SalesChannelApiSource` / `AdminApiSource` distinction
  used in the admin-order signal check.
- Shopware core `OrderDefinition::$createdById` — the signal
  that distinguishes admin-created orders from storefront
  orders.
- Shopware core
  `\Shopware\Core\Framework\Api\Context\AdminApiSource` —
  context source that identifies Admin API callers.
- [`refunds.md`](refunds.md) — independent but adjacent;
  refunds continue to operate on the `tr_*` payment id that the
  link resolves to.
- [`paypal-express-orders-api.md`](paypal-express-orders-api.md)
  — unrelated technically, but establishes the "per-handler
  routing via marker interface" pattern referenced when talking
  about multiple Mollie resource types in the same codebase.
