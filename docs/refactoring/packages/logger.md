# Package: Logger

**Path:** `shopware/Component/Logger/`  
**Namespace:** `Mollie\Shopware\Component\Logger\*`  
**Coverage (as of 2026-04-22):** 63/142 statements = **44.4 %**  
**Files in scope:** 7

## Description

Logger infrastructure: PluginSettingsHandler, RecordAnonymizer, Processor.

## Priority

Wave 1: finish Processor. Rest is already partially covered (44.4%).

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [x] | `Component/Logger/RecordAnonymizer.php` | 43 | 100 % | – | – |
| [x] | `Component/Logger/CleanUpLoggerScheduledTaskHandler.php` | 39 | – | `tests/Unit/Logger/CleanUpLoggerScheduledTaskHandlerTest.php` | – |
| [x] | `Component/Logger/PluginSettingsHandler.php` | 21 | 95 % | – | – |
| [x] | `Component/Logger/OrderLogStorage.php` | – | – | `tests/Unit/Logger/OrderLogStorageTest.php` | – |
| [ ] | `Component/Logger/OrderFileHandler.php` | 15 | 0 % | – | – |
| [ ] | `Component/Logger/Processor/MolliePluginVersionProcessor.php` | 3 | 0 % | – | – |
| [ ] | `Component/Logger/CleanUpLoggerScheduledTask.php` | 2 | 0 % | – | – |

## Integration Tests

Minimal integration surface — the logger itself is a pure service. The only
DAL-touching class here is the cleanup scheduled task.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- `Component/Logger/CleanUpLoggerScheduledTask.php` + its handler —
  integration test that verifies old log rows are deleted from
  `mollie_payments_log` (or the equivalent table) after the configured
  retention period.

Unit only: every other file in this package.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | `Component/Logger/CleanUpLoggerScheduledTaskHandler` | DAL delete of old log rows | – | – |

## Notes

- `WebhookStatusPaidEventSubscriber` was removed (pre-release task 2). Logs are
  no longer deleted immediately on the `paid`/`authorized` webhook. Retention is
  now handled entirely by `CleanUpLoggerScheduledTaskHandler`, based on the
  per-order transaction status:
  - successful orders (`paid`/`authorized`) → `logSuccessDays` (config, default 7)
  - failed/cancelled/expired or unknown orders → `logFailedDays` (config, default 30)
- The handler is file-based (reads `var/log/mollie/order-{orderNumber}.log`), not
  DB-row based. It batches a single DAL `order.repository` query per run
  (`EqualsAnyFilter` on `orderNumber`) and deletes at most `MAX_DELETE_PER_RUN`
  (100) files per run.
- `OrderLogStorage` is the **single access point** for the per-order log files.
  Both the writer (`OrderFileHandler`) and the cleanup go through it, so the
  `order-{orderNumber}.log` naming convention and the storage location live in
  exactly one class. If logs ever move off the local filesystem (e.g. a
  Flysystem/`FilesystemInterface` backend), `OrderLogStorage` is the only class
  that changes — writer and cleanup stay automatically symmetric.
- Order logging is routed by **context**, not by channel: there is a single
  `mollie` Monolog channel/logger. General logs go to the rotating
  `var/log/mollie_<env>.log` (PluginSettingsHandler); a log call additionally
  lands in a per-order file `var/log/mollie/order-{orderNumber}.log` only when it
  carries an `orderNumber` context (set manually at the call sites).
