# Package: StatusUpdate

**Path:** `shopware/Component/StatusUpdate/`  
**Namespace:** `Mollie\Shopware\Component\StatusUpdate\*`  
**Coverage (as of 2026-04-22):** 12/21 statements = **57.1 %**  
**Files in scope:** 4

## Description

StatusUpdate classes (order/transaction status sync).

## Priority

Wave 1: finish the remaining ~43% (21 stmts).

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [x] | `Component/StatusUpdate/UpdateStatusAction.php` | 12 | 100 % | – | – |
| [ ] | `Component/StatusUpdate/UpdateStatusTaskHandler.php` | 5 | 0 % | – | – |
| [ ] | `Component/StatusUpdate/UpdateStatusResult.php` | 2 | 0 % | – | – |
| [ ] | `Component/StatusUpdate/UpdateStatusScheduledTask.php` | 2 | 0 % | – | – |

## Integration Tests

Candidates: the status-update scheduled task polls Mollie for open
transactions and writes state back via the state handler.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- `Component/StatusUpdate/UpdateStatusScheduledTaskHandler` — integration
  test with a fixture order + Mollie sandbox transaction that changes state.

Unit only: the `ScheduledTask` class itself (config), events, DTOs.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | `Component/StatusUpdate/UpdateStatusScheduledTaskHandler` | Mollie poll + DAL state write | – | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
