# Domain Knowledge

Behaviour of Mollie and of this plugin that the code depends on but cannot state itself.
Read this before touching payment status, webhooks, captures, amounts and taxes, Apple Pay
Direct, subscriptions or voucher handling. Everything here was confirmed by the maintainers —
it is not inferred from the code.

Rules live in `php.md` and `testing.md`. This file holds facts, so it grows only when
something turns out to be non-obvious. Nothing here is a coding rule.

---

## Payment status comes from the webhook, never from the return

In a live shop the payment status of a transaction is set **only** by Mollie's webhook
(`/api/mollie/webhook/{transactionId}`, `Component/Payment/Route/WebhookRoute`). The customer's
return to the shop does not touch the status.

It used to do both. Webhook and return then arrived at the same moment and the status was set
twice — that race is the reason for the current split. Do not add a status change to the return
path.

**Locally there is no webhook**, because the test system is not reachable from the internet.
`MOLLIE_DEV_MODE=1` closes that gap: `DevWebHookSubscriber` calls the very same webhook route on
the finalize, shipment and cancel events. It is a development crutch, not a second production
code path.

**Maintenance mode blocks the webhook.** To verify in maintenance mode that payments really work,
allow Mollie's addresses in the sales channel — `curl https://ip-ranges.mollie.com/ips.txt`. Do
**not** reach for `MOLLIE_DEV_MODE` instead: once maintenance mode is switched off again, the
payment status can end up being changed twice.

## What one webhook call does

Mollie sends a webhook for **every** change on its side, and retries a failed call
(schedule: https://docs.mollie.com/reference/webhooks#retry-schema). Everything below therefore
has to survive being executed more than once for the same payment.

Per call the plugin loads the payment from Mollie and then

1. updates the payment status of the Shopware transaction,
2. refreshes the stored payment details (the transaction custom fields),
3. corrects the payment method — the customer may have picked a different one at Mollie,
4. maps the payment status onto the order status, if the merchant configured the order state
   mapping.

The webhook payload is the fresh truth; the Shopware entities loaded in the same request may
already be stale against it (`autoCaptureDigitalItems` depends on exactly that).

## Authorized is not paid: pay-later methods

Some methods never jump straight to `paid`. Klarna, Billie, Billink and Riverty reach
`authorized` first — the money is only reserved. It is captured, and the status becomes `paid`,
once the merchant has shipped the order. Handlers of such methods carry
`Component/Payment/Handler/ManualCaptureModeAwareInterface`.

**Digital products are the exception.** They cannot be shipped, and Shopware has no delivery
state for an order consisting only of downloads, so no shipment event would ever capture them.
The webhook therefore captures digital line items itself as soon as the payment is `authorized`:
a digital-only order in full — it reaches `paid` through Mollie's follow-up webhook — a mixed
order only partially, its physical part still being captured on the real shipment.

## Deprecated methods stay installed — Klarna, Sofort, SEPA Direct Debit

A payment method Mollie has retired is **deactivated, never deleted**. Deleting the row breaks
Shopware's order overview for every old order that used it, and invoices for those orders can no
longer be generated. The mechanism for new cases is
`Component/Payment/Handler/DeprecatedMethodAwareInterface` (see the `payment-methods` skill);
what follows are the three that generate support tickets.

**Klarna.** Mollie merged the separate methods — *Slice It*, *Pay Later*, *Pay Now* and bank
transfer — into a single **Klarna**
([official statement](https://help.mollie.com/hc/en-gb/articles/20406623599890-Klarna-Migration)).
Not every merchant has been migrated yet, and the recurring ticket is "Klarna does not work" while
the log shows the old *Slice It* or *Pay Later* being used. The answer is always the same: those
methods are deprecated, deactivate them and use Klarna.

**Sofort / Direktüberweisung.** Mollie discontinued it on **30 September 2024**
([official statement](https://help.mollie.com/hc/en-us/articles/20904206772626-SOFORT-Deprecation-30-September-2024)),
and merchants still ask for it.

**SEPA Direct Debit.** Mollie still has `directdebit`, but it cannot be used for a regular or an
initial payment — only to charge an existing mandate. The plugin added it in **2.3.0**
(2022-07-13) and removed it as a checkout method again in **3.2.0** (2022-10-13);
`DirectDebitPayment` still exists and carries `DeprecatedMethodAwareInterface`.

There is **no deprecation announcement** for it the way there is for Sofort and Klarna, so nothing
can be linked that says "this was switched off". What can be shown to a merchant instead is
Mollie's own documentation, which never offers direct debit as a payment the customer starts:

- [Recurring payments](https://docs.mollie.com/docs/recurring-payments) — the first-payment table
  lists credit card (incl. Apple Pay, Google Pay), PayPal, Belfius, Bacs, Bancontact, EPS, iDEAL,
  KBC and PayByBank. `directdebit` is not among them; those methods *create* the direct debit
  mandate, which is why the page says to enable SEPA Direct Debit in the Mollie profile for them.
- [SEPA Direct Debit](https://docs.mollie.com/docs/sepa-direct-debit) — "The standard method for
  collecting Euro-denominated **recurring** payments", chargeable only "after the first payment is
  completed successfully".
- Merchant-facing version of the same list:
  [How do I use Recurring Payments via Mollie?](https://help.mollie.com/hc/en-us/articles/115000967505-How-do-I-use-Recurring-Payments-via-Mollie)

Mollie does collect one-off direct debits in the **Business Account** product; that is a different
product and not the Payments API, so it is not an argument for a checkout method here.

The method is not dead at runtime, though: `directdebit` is what Mollie reports for a
**subscription renewal**, which is why `PaymentMethodUpdater` returns early for it instead of
rewriting the order's payment method.

Timeline for Klarna and Sofort, so nobody has to dig for it again — unlike SEPA, the changelog
does **not** carry these:

- **4.21.0** — Klarna Pay Later, Pay Now, Slice It and Sofort are force-deactivated on every
  plugin update (PR #1175).
- **5.0.0** — the handlers are gone with the Payments API refactor. On 5.x these methods exist
  only as inactive rows in the database, which is exactly what keeps the old orders intact.

## Payments API since 5.x — the Orders API is legacy, not dead

Since 5.x the plugin creates payments through Mollie's **Payments API**. The Orders API is still
implemented, but only for tests (`*OrdersApiPayment` handlers, `OrdersApiAwareInterface`) and for
one open gap: PayPal Express needs a field the Payments API does not offer yet.

The Orders API branches in refund and shipment must not be removed. An order can have been placed
on 4.x before the merchant updated to 5.x, and that order still has to be shippable and
refundable — an Orders API order uses different endpoints for capture and refund than a Payments
API one. Which path applies is decided per order by the stored Mollie id (`order_id` in the custom
fields), never by the installed plugin version.

## Custom fields: the transaction is the source of truth

Since 5.x the plugin reads its own data **only** from the order transaction
(`customFields['mollie_payments']`). The same block is additionally written to the order, but
solely so third-party plugins and ERP connectors keep working
(`TransactionService::savePaymentExtension`).

Never let plugin logic depend on the copy at the order: it exists for compatibility. New data goes
on the transaction.

---

## Order state mapping is the simple alternative to the Flow Builder

Shopware's own answer to "set the order state when the payment status changes" is the Flow
Builder. Not every merchant manages to set that up, so the plugin offers a configuration that
maps a payment status directly onto an order state (`Settings/Struct/OrderStateSettings`,
applied by the webhook).

That mapping is deliberately a quick fix for **simple setups**. A shop with its own flows and
custom events should use the Flow Builder instead and leave the mapping unconfigured —
otherwise two mechanisms move the same order state. Do not grow the mapping into a small flow
engine; the answer to "can it also do X?" is the Flow Builder.

## The rounding difference line item exists so the payment goes through

Shopware lets the merchant configure the decimal precision and the rounding interval **per
currency**. Dutch shops set the total to round to **0.05**, and they are not being quirky: cash
payments must be rounded to the nearest five cents by law there since 2004. The same rule
applies in Finland, Ireland, Belgium, Italy, Slovakia, Lithuania and Estonia, and Swiss shops
round CHF to 0.05 as well — so this is a whole group of markets, not one country. The law only
covers cash, but a shop that wants one price everywhere configures the interval for the whole
currency. The Mollie API, meanwhile, allows two decimals, full stop.

Between that precision gap and the tax calculation, the sum of the line items regularly does
not match the order total. Mollie rejects such a payment. `RoundingDifferenceFixer` closes the
gap with an extra line item (`mollie-rounding-diff`, a surcharge or a discount, VAT rate 0)
whose amount is exactly the difference. The amount is also persisted as `rounding_diff` in the
custom fields, so the first shipment capture can add it back to the captured amount.

It is not cosmetic and it is not a bug to be "cleaned up": without it the payment does not get
created at all.

## Taxes are recalculated, not passed through

Two mismatches force the plugin to compute taxes itself instead of forwarding what Shopware
delivers.

- **One VAT rate per line.** A Shopware line — shipping costs, a voucher — can carry several
  tax rates at once (7 % on the product, 21 % on the shipping). Mollie accepts a single
  `vatRate` plus `vatAmount` per line item, so the rates have to be blended into one average
  rate over the net base (`LineItem::calculateTax`).
- **Only vertical tax calculation.** Shopware knows vertical and horizontal tax calculation,
  Mollie only vertical.

On top of that Mollie validates `vatAmount === totalAmount × vatRate / (100 + vatRate)` on the
values actually transmitted and rejects the payment with *"The 'vatAmount' field is off"* if
they disagree. Whenever an amount that goes to Mollie changes, the VAT amount has to be
re-derived from that same transmitted amount.

## Since 5.x there is one log file per order

As soon as an order number is known, everything Mollie-related for that order is written to its
own file: `%kernel.logs_dir%/mollie/order-<orderNumber>.log`, usually
`var/log/mollie/order-12345.log` (`OrderLogStorage`, `OrderFileHandler`). Before an order number
exists, the records go to the general Mollie log.

**The API payloads and the API responses are in there.** That is deliberate — it is what makes
support possible without asking the merchant to reproduce anything. Debug mode in the plugin
configuration adds more on top. Personal data does not end up in there unmasked: `RecordAnonymizer`
masks the context and anonymises the IP.

Ask for that one file first when a merchant reports a problem with a concrete order.

**Retention**, so the log directory cannot fill up the server's disk
(`CleanUpLoggerScheduledTaskHandler`, configurable in the plugin settings):

- successful orders — **7 days** by default. Once the payment went through, the file has served
  its purpose.
- everything else — **30 days** by default, because a failed payment is often noticed much later.
- "successful" is `paid` **or** `authorized`, not just `paid` — a pay-later order is not kept for
  30 days.
- The task deletes at most 100 files per run, so on a big shop the directory shrinks over several
  runs instead of at once.

## "The payment method is not visible in the checkout"

A recurring support question with several possible causes. Verify them in this order before
looking at code:

1. Is the payment method active in the shop at all?
2. Is it assigned to the sales channel?
3. Is **"Use Mollie's availability rules for payment methods"** (`useMolliePaymentMethodLimits`)
   enabled in the plugin configuration?

The plugin hides a method **only** when that setting is on
(`Component/Payment/MethodRemover/AvailabilityPaymentMethodRemover`). If it is off and the method
is still missing, the cause is in Shopware, not in this plugin.

Why the feature exists: a method must additionally be activated in the **Mollie dashboard**, and
that is not immediate — the merchant clicks activate and then waits for the approval, which is
easily overlooked; only an actually ticked checkbox counts. Mollie also restricts methods by
currency, by billing country and by a minimum and a maximum order amount. A merchant who assigns
iDEAL to the sales channel without having it approved at Mollie sends the customer into a
checkout that must fail. The availability rules prevent exactly that: they ask Mollie which
methods are usable for this cart amount, currency and billing country, and remove the rest.

So "why is method X missing although the rules are switched on" is answered at the Mollie
dashboard, at the amount limits or at the billing country — rarely in the plugin.

## Apple Pay Direct: it is almost always the `.well-known` file

The most frequent cause of "Apple Pay Direct does not work" is the domain verification file.
It is downloaded automatically as soon as Apple Pay Direct is enabled in the plugin
configuration, and `bin/console mollie:applepay:download-verification` fetches it again.

The file lands in the shop's `public/.well-known/` folder — and in setups with a CDN or a
split document root it is not reachable from the outside from there. So the check is always
the same: call
`https://<shop-domain>/.well-known/apple-developer-merchantid-domain-association` in a browser.
Only if that file is served does Apple Pay Direct work. A merchant reporting the feature as
broken should be asked for that URL before anything in the code is investigated.

---

## Subscriptions: the start date is the charge date

At Mollie, a subscription's `startDate` decides **when money is actually taken**. It is not
a bookkeeping field. Consequently it practically always lies in the future.

That is why the normal checkout does not start the subscription today: the first payment
already happened during checkout, so the subscription is created with the **next** due date
as its `startDate`. Starting it today would charge the customer a second time.

**Mollie has no "skip" and no "pause".** There are only two operations: create with a start
date, and cancel. The plugin emulates both by cancelling the running subscription and
creating a replacement whose `startDate` lies one interval later. The skipped charge exists
only because of that date — it carries the entire feature.

What follows for anything that creates, copies or moves a subscription:

- Treat `startDate` as payment-relevant. A wrong value is not cosmetic; it moves or triggers
  a real charge.
- A `startDate` in the past means the customer is charged at the next possible moment — the
  opposite of skipping or pausing.
- `Subscription::skipPayment()` computes `nextPaymentDate + interval`, which is the correct
  rule. Do not "simplify" it to `today + interval`.
- The object returned by `cancelSubscription()` is rebuilt from Mollie's response and carries
  **Mollie's** start date, not any value set locally beforehand. Anything computed before the
  cancel call has to be re-applied afterwards.

The last point caused a real defect: `SkipAction` set the shifted start date, then overwrote
the whole object with the cancel response, so the replacement subscription was created with
the original creation date. `SkipActionTest` now covers it.

### Interval strings

Mollie writes the unit in the singular for a single period (`1 month`, not `1 months`) and
expects the same back. `Interval::__toString()` produces that form; `Interval::fromString()`
accepts both. The string is also used as a runtime grouping key for renewals, but it is never
persisted — `mollie_subscription` stores `interval_value` and `interval_unit` separately and
rebuilds the `Interval` on read.

---

## Vouchers: three of six categories are exposed on purpose

`Component/Mollie/VoucherCategory` knows six categories: `eco`, `gift`, `meal`,
`sport_culture`, `consume` and `additional`. The admin dropdown built by
`CustomFieldsInstaller` offers only `eco`, `meal` and `gift`.

**This gap is intentional, not an oversight.** The three additional categories were
implemented ahead of time from Mollie's public API documentation; Mollie has not yet cleared
them for use, so they stay out of the merchant-facing dropdown until that approval arrives.

For tests: assert that every offered value *is* a valid `VoucherCategory` — never assert
equality with `VoucherCategory::cases()`, or the test breaks on the day the remaining three
are switched on.
