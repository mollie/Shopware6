# Package: Transaction

**Path:** `shopware/Component/Transaction/`  
**Namespace:** `Mollie\Shopware\Component\Transaction\*`  
**Coverage (as of 2026-04-22):** 0/174 statements = **0.0 %**  
**Files in scope:** 9

## Description

Transaction data handling: TransactionService, TransactionDataException, exception subclasses.

## Priority

Wave 1: TransactionDataException and other exception classes.
Wave 2: TransactionService (81 stmts) with FakeTransactionRepository.

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Component/Transaction/TransactionService.php` | 81 | 0 % | – | – |
| [ ] | `Component/Transaction/TransactionDataException.php` | 63 | 0 % | – | – |
| [ ] | `Component/Transaction/TransactionDataStruct.php` | 10 | 0 % | – | – |
| [ ] | `Component/Transaction/PaymentTransactionStruct.php` | 8 | 0 % | – | – |
| [ ] | `Component/Transaction/TransactionConverter.php` | 6 | 0 % | – | – |
| [ ] | `Component/Transaction/Exception/OrderWithoutCustomerException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Transaction/Exception/OrderWithoutDeliveriesException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Transaction/Exception/OrderWithoutTransactionException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Transaction/Exception/TransactionException.php` | 0 | 0 % | – | – |

## Integration Tests

Candidates: transaction builders / services that read or write
`order_transaction` or interact with Mollie transactions.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- **TransactionBuilder / TransactionService** — write custom fields to
  `order_transaction`, integration test via `OrderTransactionRepository`.
- Anything that resolves a transaction by Mollie id (DAL read by custom
  field).

Unit only: `Exception/TransactionException`, structs, events.

Reference: `tests/Integration/Repository/OrderTransactionRepositoryTest.php`.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | _(to be filled per wave)_ | – | – | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
