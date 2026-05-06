# Subscription price updates

How running Mollie subscriptions react when a merchant changes the price of
a subscription product.

---

## Two settings, per sales channel

Located under **Settings → Plugins → Mollie Payments → Subscriptions**.

| Setting | Values | Default | Effect |
|---|---|---|---|
| `Behaviour when a subscription product price changes` | `keep` / `auto` | `keep` | `keep` leaves running subscriptions untouched. `auto` notifies the customer, gives them a cancel window, then updates the subscription amount automatically. |
| `Notice period (days) before a subscription price change applies` | integer ≥ 0 | `0` | How many days the customer has between receiving the notice mail and the new price taking effect. `0` means the change is migrated on the next scheduled-task run. |

Both fields are localized into all 23 plugin languages.

---

## What happens when `auto` mode is enabled

A daily scheduled task (`mollie.subscription_price_update.task`) runs two
phases against every sales channel that has `auto` selected:

1. **Detect.** For each active or resumed subscription, rebuild the cart at
   current product prices and compare the total to
   `mollie_subscription.amount`. When they differ, the row is flipped to
   `priceUpdateState = notified`, `nextNotifiedPrice` is stored, and the
   `mollie.subscription.priceChangeNotice` Flow Builder event fires (which
   sends the configured mail template).
2. **Migrate.** For every subscription whose `notifiedAt + noticeDays` is in
   the past, the handler issues a `PATCH` to Mollie's subscription endpoint
   with the new amount. On success, the local `mollie_subscription.amount`
   is updated and the state is cleared. A history entry `price_migrated`
   is written.

If the customer cancels the subscription before the notice window elapses,
the migrate phase skips it (cancelled subscriptions are filtered out).

If anything throws during detect or migrate, the row stays at its current
state and a `price_check_skipped` or `price_migration_failed` history entry
records the reason. The next run retries.

---

## The notice mail

Mail-template type: `mollie_subscription_price_change`. Both DE and EN
versions ship with the plugin and can be edited in the standard Shopware
admin (Settings → Email templates). Template variables:

- `subscription` — the `mollie_subscription` entity
- `customer` — the Shopware customer
- `subscription.order.lineItems` — line items of the original order

The Flow Builder trigger is named `mollie.subscription.priceChangeNotice`.
Bind any additional flow actions (e.g. log to ticket system) to that event.

---

## What customers see

The account page (`/account/mollie/subscriptions`) renders a yellow
notice on every subscription row whose `priceUpdateState` is `notified`:

- Headline: "Upcoming price change"
- Current price → new price
- Effective date (`notifiedAt + noticeDays`)
- Prominent **Cancel subscription** button — same endpoint as the context
  menu's cancel action

The notice disappears once the migrate phase completes (state goes back to
`none`).

---

## Edge cases

**Deleted subscription product.** If the merchant removes a product that a
running subscription references, the cart rebuild throws. The detector
catches it, logs an error with `subscriptionId`, `mollieId`, `customerId`,
`orderId`, writes `price_check_skipped` to history, and skips the row —
the customer is **not** mailed. Merchant action: cancel the subscription
manually and contact the customer. Affected subscriptions surface in
`var/log/mollie_*.log`.

**Price decrease.** Treated identically to a price increase — the customer
still gets the notice and the cancel window. Switch to a different policy
later if merchants ask for it (a two-line change in the detector).

**No mode override per subscription.** The setting is sales-channel
scoped. There is no admin UI to opt a single subscription out.
