# Feature: Subscription Price Update Workflow

**Status:** Planned — implementation steps below; pick up one step per session.
**Owner:** Vitalij Mik
**Created:** 2026-05-06
**Last updated:** 2026-05-06
**Parent feature:** [`subscriptions.md`](./subscriptions.md) — Phase 5

---

## Goal

Allow a merchant to change the price of a subscription product and have running
Mollie subscriptions adopt the new price automatically, with the legally
required customer notice and cancel window before the change takes effect.

## Non-goals

- No `mollie_subscription_line_item` aggregate. The drift detector reads the
  original order's line items at runtime via the existing
  `SubscriptionGroupCartBuilder`. The big Phase 2 data migration from the
  parent feature doc is **not** required for this workflow.
- No automatic handling for deleted subscription products. Logged + skipped;
  merchant intervenes manually. Documented in the merchant wiki.
- No discrimination between price increase and price decrease. Both go through
  the notice window. (Revisit later if merchants ask for it.)
- No per-subscription override of the update mode. Setting is per sales
  channel.

---

## Decisions (carried over and refined from `subscriptions.md`)

1. **Update mode** is per sales channel, dropdown with two values:
   `keep` (default, current behaviour) and `auto` (notify → migrate).
2. **Mollie Update API first.** `SubscriptionGateway::updateSubscription()`
   already exists and Mollie supports `amount` updates via PATCH. Cancel +
   recreate is the **fallback** when the PATCH returns a `ClientException`,
   not the primary path.
3. **Notice window** uses a new dedicated setting `priceUpdateNoticeDays`,
   not reused from `reminderDays` / `cancelDays`.
4. **No aggregate, no backfill.** Three new columns on `mollie_subscription`
   are sufficient. Default state covers all existing subscriptions.
5. **Drift computation** uses `SubscriptionGroupCartBuilder` against the
   original order's line items, filtered by interval, summed. Compared to
   `mollie_subscription.amount`.
6. **Deleted product** (rare; legally orders must be retained 10 years):
   detector catches the exception, logs error with subscription/customer/order
   IDs, writes history `price_check_skipped`, skips the row. No customer
   mail, no automatic merchant mail. Wiki documents that the merchant must
   cancel manually and contact the customer.
7. **One scheduled task** runs both phases: detect drift, then migrate due
   notifications. Keeps operations simple.

---

## Steps

Each step = one commit on the active beta branch (no separate branches per
step). `make pr` must pass at the end of each step before committing. Order
matters; later steps depend on earlier ones.

---

### Step 1 — Schema migration: three columns on `mollie_subscription`

**Files to create:**
- `src/Migration/MigrationXXXXXXXXXXSubscriptionPriceUpdateState.php`
  - Add `price_update_state` VARCHAR(16) NOT NULL DEFAULT `'none'`
  - Add `next_notified_price` DECIMAL(10,2) NULL
  - Add `notified_at` DATETIME(3) NULL

**Files to edit:**
- `shopware/Component/Subscription/DAL/Subscription/SubscriptionDefinition.php`
  - Add the three fields (`StringField`, `FloatField`, `DateTimeField`)
- `shopware/Component/Subscription/DAL/Subscription/SubscriptionEntity.php`
  - Properties + getters/setters

**Reference:** `src/Migration/Migration1768084646SubscriptionOrderVersionId.php`
for the migration shape.

**Tests:**
- `tests/Unit/Subscription/DAL/EntityDefinitionsTest.php` — add the three
  field assertions
- `tests/Unit/Subscription/DAL/SubscriptionEntityTest.php` — getters/setters

**Done-when:** migration runs cleanly on an existing dump; entity round-trips
the new fields.

---

### Step 2 — Settings: dropdown + notice days

**Files to edit:**
- `src/Resources/config/config.xml` — add two `<input-field>` blocks in the
  subscriptions section (location: after `subscriptionsCancellationDays` at
  line ~3342):
  - `subscriptionsPriceUpdateMode` (single-select: `keep` | `auto`,
    default `keep`)
  - `subscriptionsPriceUpdateNoticeDays` (int, default `0`)
- `shopware/Component/Settings/Struct/SubscriptionSettings.php`
  - New `KEY_PRICE_UPDATE_MODE`, `KEY_PRICE_UPDATE_NOTICE_DAYS`
  - Add to constructor + `createFromShopwareArray` + getters
  - Helper `isAutoPriceUpdate(): bool` for ergonomics

**Tests:**
- Unit test for `SubscriptionSettings::createFromShopwareArray` covering both
  default and explicit values

**Done-when:** admin UI shows both fields; values reach `SubscriptionSettings`
correctly.

---

### Step 3 — Mail template via DB migration

**Files to create:**
- `src/Migration/MigrationXXXXXXXXXXSubscriptionPriceChangeMailTemplate.php`
  - Mail template type `technical_name = mollie_subscription_price_change`
  - DE + EN translations
  - Variables exposed: `subscription`, `customer`, plus implicit Twig access
    to `subscription.order.lineItems`
- `shopware/Resources/views/email/mollie_subscription_price_change/html.twig`
- `shopware/Resources/views/email/mollie_subscription_price_change/plain.twig`
  - Render: customer name, current amount, new amount, next renewal date,
    cancel deadline, line-item breakdown filtered by interval, link to
    cancel page

**Reference:** `src/Migration/Migration1777881160RenewalReminderMailTemplate.php`
end-to-end (ensureMailTemplateType, ensureMailTemplate, both translations).

**Tests:**
- Integration test that runs the migration and asserts a row exists in
  `mail_template` with the expected `technical_name`

**Done-when:** the merchant can edit the new template in the standard
Shopware admin mail-template UI.

---

### Step 4 — Event for the notice mail

**Files to create:**
- `shopware/Component/Subscription/Event/SubscriptionPriceChangeNoticeEvent.php`
  - Extends `SubscriptionActionEvent`
  - `getEventName(): string { return 'priceChangeNotice'; }`
  - Resulting Flow Builder name: `mollie.subscription.priceChangeNotice`
  - Optionally override `getAvailableData()` to expose `oldAmount`,
    `newAmount`, `cancelDeadline` if needed by the template

**Files to edit:**
- `shopware/Component/FlowBuilder/Subscriber/BusinessEventSubscriber.php`
  if the existing reminder pattern requires registration of the new event

**Reference:** `shopware/Component/Subscription/Event/SubscriptionRemindedEvent.php`
(2 lines, perfect template).

**Tests:**
- `tests/Unit/Subscription/Event/SubscriptionEventsTest.php` — add the new
  event to the fixture list

**Done-when:** event is dispatchable; Flow Builder shows it in the trigger
list; mail-template type from Step 3 is bound to it.

---

### Step 5 — `PriceDriftDetector` + scheduled task (detect phase)

**Files to create:**
- `shopware/Component/Subscription/PriceDrift/PriceDriftDetector.php`
  - Iterates sales channels via `sales_channel.repository`
  - Per channel: skips when `priceUpdateMode !== 'auto'`
  - Loads candidate subscriptions: status = active/resumed,
    `price_update_state = none`, `canceledAt = null`
  - For each candidate:
    - Build temp cart via `SubscriptionGroupCartBuilder` from original order +
      subscription's interval
    - Sum line totals → `expectedAmount`
    - If `expectedAmount !== subscription.amount`:
      - Set `price_update_state = notified`,
        `next_notified_price = expectedAmount`, `notified_at = now`
      - Dispatch `SubscriptionPriceChangeNoticeEvent`
      - Write history entry `price_notified`
    - On exception (deleted product etc.): logger.error with
      `subscriptionId`, `mollieId`, `customerId`, `orderId`; history
      `price_check_skipped`; continue
- `shopware/Component/Subscription/ScheduledTask/SubscriptionPriceUpdateTask.php`
- `shopware/Component/Subscription/ScheduledTask/SubscriptionPriceUpdateTaskHandler.php`
  - Runs detect + migrate (Step 6) in this order
  - Default interval: 86400 (daily)

**Files to edit:**
- Plugin install / scheduled-task registration so the task entry exists in
  `scheduled_task` table on plugin update

**Reference:**
- Detector shape: `shopware/Component/Subscription/SubscriptionRenewalReminder.php`
- Task shape: `shopware/Component/Subscription/ScheduledTask/SubscriptionRenewalReminderTask.php`
  + `…Handler.php`
- Cart build: `shopware/Component/Subscription/SubscriptionGroupCartBuilder.php`

**Tests:**
- Unit `PriceDriftDetectorTest`:
  - drift detected → state transition + event dispatched
  - already notified → skipped
  - mode = keep → skipped
  - exception during cart build → logger.error + history + no event
- Smoke test for the task handler

**Done-when:** running the task on a fixture (price changed) flips the
matching subscription to `notified` and fires the event once.

---

### Step 6 — `PriceMigrationHandler` (migrate phase)

**Files to create:**
- `shopware/Component/Subscription/PriceDrift/PriceMigrationHandler.php`
  - Per sales channel, loads subs with `price_update_state = notified` AND
    `notified_at + priceUpdateNoticeDays <= today`
  - For each:
    1. Build a `Subscription` value object with the new `amount`
    2. Try `SubscriptionGateway::updateSubscription()`
    3. On success: clear `price_update_state` back to `none`, null
       `notified_at` and `next_notified_price`, update local
       `mollie_subscription.amount`, history `price_migrated`
    4. On `ClientException` from Mollie: fallback path —
       `cancelSubscription` + `createSubscription` with copied fields
       (description, interval, mandate, metadata, webhook, `timesRemaining`),
       new `amount`. History `price_migrated_via_recreate`. Replace
       `mollieId` on the local row.
    5. On other exception: logger.error, history `price_migration_failed`,
       leave state at `notified` so next run retries

**Files to edit:**
- `shopware/Component/Subscription/ScheduledTask/SubscriptionPriceUpdateTaskHandler.php`
  - Wire migrate phase after detect phase

**Reference:**
- Update API: `shopware/Component/Mollie/Gateway/SubscriptionGateway.php::updateSubscription`
- Recreate: `…SubscriptionGateway.php::copySubscription` (copy field set)

**Tests:**
- Unit `PriceMigrationHandlerTest`:
  - notice window not elapsed → skip
  - update API success → state cleared, history `price_migrated`
  - update API throws ClientException → recreate path, history
    `price_migrated_via_recreate`, new `mollieId` persisted
  - other exception → state preserved, history `price_migration_failed`
- Integration test against Mollie sandbox: real subscription, real PATCH

**Done-when:** end-to-end happy path goes detect → mail → wait → migrate via
PATCH; subscription row reflects new amount and new mollie state.

---

### Step 7 — Storefront notice on the account page

**Files to edit:**
- `src/Resources/views/storefront/page/account/subscriptions/subscription-item.html.twig`
  - When `subscription.priceUpdateState === 'notified'`: render an info box
    with current → new amount, effective date (`notified_at +
    priceUpdateNoticeDays`), prominent cancel button (existing endpoint)

**Tests:**
- One Cypress spec under `tests/Cypress/cypress/e2e/subscription/`
  exercising "auto mode set, drift detected, notice visible on account page,
  cancel still works"

**Done-when:** customer sees the notice and can cancel within the window
without touching admin.

---

### Step 8 — Behat E2E + docs

**Behat scenarios** in `tests/Behat/Features/subscription-price-update.feature`:

- A: mode = `keep`, price changes → subscription unchanged, no mail
- B: mode = `auto`, drift detected, customer cancels within window → no
  migration, plain cancel
- C: mode = `auto`, notice window elapsed → update API hit, subscription
  amount updated
- D: mode = `auto`, update API rejects → recreate path runs, new mollieId

Renewal-side actions can use the dev-mode bypass in `RenewRoute.php` if
needed.

**Docs:**
- Edit `docs/refactoring/features/subscriptions.md` — mark Phase 5 as live
- Create `docs/configuration/subscription-price-updates.md` (merchant view):
  - What the two settings do
  - How to edit the mail template
  - What customers see
- Wiki entry (separate from repo, merchant-facing): "Deleted product
  during active subscription — manual cancel + customer contact required.
  See `var/log/mollie_*.log` for affected subscription IDs."

**Done-when:** all four Behat scenarios green in CI; docs merged.

---

## Open questions to revisit later

1. **Confirmation mail after migration** (a `SubscriptionPriceMigratedEvent`
   + second mail template). Nice-to-have; folds in cleanly after Step 6 if
   merchants ask for it.
2. **Asymmetric handling for price decreases** (skip notice window). Two-line
   change in the detector if needed; revisit when a merchant requests it.
3. **Per-subscription overrides** (one customer keeps old price by deal).
   Out of scope for v1; would need an admin UI on the subscription detail
   page.

---

## References (current code to mirror)

- `shopware/Component/Subscription/SubscriptionRenewalReminder.php` — sales
  channel iteration, candidate filtering, history writes
- `shopware/Component/Subscription/ScheduledTask/SubscriptionRenewalReminderTask.php`
  + handler — scheduled-task wiring
- `shopware/Component/Subscription/Event/SubscriptionRemindedEvent.php` —
  event shape
- `src/Migration/Migration1777881160RenewalReminderMailTemplate.php` —
  mail-template DB migration
- `shopware/Component/Mollie/Gateway/SubscriptionGateway.php` — update +
  copy (fallback)
- `shopware/Component/Subscription/SubscriptionGroupCartBuilder.php` —
  drift computation source
- `shopware/Component/Settings/Struct/SubscriptionSettings.php` — settings
  pattern
