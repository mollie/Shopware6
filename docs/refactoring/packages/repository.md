# Package: Repository

**Path:** `shopware/Repository/`  
**Namespace:** `Mollie\Shopware\Repository\*`  
**Coverage (as of 2026-04-22):** 0/31 statements = **0.0 %**  
**Files in scope:** 1

## Description

Custom repository classes under `shopware/Repository/`: OrderTransactionRepository + interface.

## Priority

Wave 2: repository test with FakeEntityRepository.

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Repository/OrderTransactionRepository.php` | 31 | 0 % | – | – |

## Integration Tests

Every class in this package is a repository wrapper — by definition
DAL-dependent, so every class gets an integration test.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- `Repository/OrderTransactionRepository.php` — covered by
  `tests/Integration/Repository/OrderTransactionRepositoryTest.php`. Extend
  with coverage for any new custom finder methods.
- Future repositories added to this folder → new test file under
  `tests/Integration/Repository/` mirroring the class name.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [/] | `Repository/OrderTransactionRepository.php` | DAL wrapper | `tests/Integration/Repository/OrderTransactionRepositoryTest.php` | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
