# Package: StateHandler

**Path:** `shopware/Component/StateHandler/`  
**Namespace:** `Mollie\Shopware\Component\StateHandler\*`  
**Coverage (as of 2026-04-22):** 81/85 statements = **95.3 %**  
**Files in scope:** 1

## Description

Order/transaction state handling.

## Priority

No action needed (95.3%). Only monitoring: do not let coverage drop.

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [x] | `Component/StateHandler/OrderStateHandler.php` | 85 | 95 % | – | – |

## Integration Tests

Candidates: `OrderStateHandler` drives the Shopware state machine. The
branching logic is covered at unit level; integration proves the state
transitions actually fire and persist in `state_machine_history`.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- `Component/StateHandler/OrderStateHandler.php` — integration test per
  main transition (paid / failed / canceled / refunded / shipped).

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | `Component/StateHandler/OrderStateHandler.php` | State machine transitions + DAL persistence | – | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
