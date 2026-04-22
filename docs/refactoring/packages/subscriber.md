# Package: Subscriber

**Path:** `shopware/Subscriber/`  
**Namespace:** `Mollie\Shopware\Subscriber\*`  
**Coverage (as of 2026-04-22):** 0/167 statements = **0.0 %**  
**Files in scope:** 7

## Description

Event subscribers under `shopware/Subscriber/`: Customer, LineItem, OrderTransaction, PaymentMethod, Product, StoreFrontData, DevWebHook.

## Priority

Wave 3: subscribers need FakeEvent/FakeEventDispatcher setups and component-specific fakes. Usually 2-4 tests per subscriber are enough.

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Subscriber/StoreFrontDataSubscriber.php` | 55 | 0 % | – | – |
| [ ] | `Subscriber/OrderTransactionSubscriber.php` | 32 | 0 % | – | – |
| [ ] | `Subscriber/LineItemSubscriber.php` | 21 | 0 % | – | – |
| [ ] | `Subscriber/DevWebHookSubscriber.php` | 18 | 0 % | – | – |
| [ ] | `Subscriber/CustomerSubscriber.php` | 15 | 0 % | – | – |
| [ ] | `Subscriber/PaymentMethodSubscriber.php` | 15 | 0 % | – | – |
| [ ] | `Subscriber/ProductSubscriber.php` | 11 | 0 % | – | – |

## Integration Tests

Subscribers are unit-tested by invoking the handler directly with a fake
event. Integration testing a subscriber in isolation usually means
re-testing the service it delegates to — instead, cover the **flow that
fires the subscriber** via a Behat scenario.
See [`../rules/integration-testing.md`](../rules/integration-testing.md) and
[`../rules/behat-testing.md`](../rules/behat-testing.md).

Integration-level targets (only if the subscriber writes to the DAL itself):

- _(none expected — add here only if a subscriber persists state directly)_

Unit only: all subscribers in this package, unless the above applies.

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
