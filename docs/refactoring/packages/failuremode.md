# Package: FailureMode

**Path:** `shopware/Component/FailureMode/`  
**Namespace:** `Mollie\Shopware\Component\FailureMode\*`  
**Coverage (as of 2026-04-22):** 0/38 statements = **0.0 %**  
**Files in scope:** 2

## Description

Payment failure handling: FailureModeOrderController, PaymentPageFailedEvent.

## Priority

Wave 1: PaymentPageFailedEvent (plain struct).
Wave 2: FailureModeOrderController.

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Component/FailureMode/FailureModeOrderController.php` | 31 | 0 % | – | – |
| [ ] | `Component/FailureMode/PaymentPageFailedEvent.php` | 7 | 0 % | – | – |

## Integration Tests

Minimal integration surface. The event class is a pure carrier; the page
service renders the failure page and relies on Shopware's page loader.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- `Component/FailureMode/PaymentPageService` (if it touches the DAL for
  session recovery) — integration test with a fixture order.

Unit only: `PaymentPageFailedEvent`, exceptions.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | _(to be filled per wave)_ | – | – | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
