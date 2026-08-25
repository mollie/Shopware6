# Domain Knowledge

Behaviour of Mollie and of this plugin that the code depends on but cannot state itself.
Read this before changing subscriptions or voucher handling. Everything here was confirmed
by the maintainers — it is not inferred from the code.

Rules live in `php.md` and `testing.md`. This file holds facts, so it grows only when
something turns out to be non-obvious. Nothing here is a coding rule.

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
