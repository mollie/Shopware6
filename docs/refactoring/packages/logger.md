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
| [ ] | `Component/Logger/CleanUpLoggerScheduledTaskHandler.php` | 39 | 0 % | – | – |
| [x] | `Component/Logger/PluginSettingsHandler.php` | 21 | 95 % | – | – |
| [ ] | `Component/Logger/WebhookStatusPaidEventSubscriber.php` | 19 | 0 % | – | – |
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

_(Space for package-specific decisions, fake requirements, special setups.)_
