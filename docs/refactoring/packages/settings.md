# Package: Settings

**Path:** `shopware/Component/Settings/`  
**Namespace:** `Mollie\Shopware\Component\Settings\*`  
**Coverage (as of 2026-04-22):** 32/259 statements = **12.4 %**  
**Files in scope:** 13

## Description

Plugin settings and struct classes (PaymentSettings, ApiSettings, LoggerSettings, EnvironmentSettings, …) plus SettingsService.

## Priority

Wave 1: finish remaining struct classes (already partially tested).
Wave 2: SettingsService (101 stmts).

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Component/Settings/SettingsService.php` | 101 | 0 % | – | – |
| [ ] | `Component/Settings/Struct/SubscriptionSettings.php` | 26 | 0 % | – | – |
| [ ] | `Component/Settings/SystemConfigSubscriber.php` | 25 | 0 % | – | – |
| [ ] | `Component/Settings/Struct/OrderStateSettings.php` | 24 | 0 % | – | – |
| [/] | `Component/Settings/Struct/PaymentSettings.php` | 20 | 55 % | – | – |
| [x] | `Component/Settings/Struct/ApiSettings.php` | 15 | 80 % | – | – |
| [ ] | `Component/Settings/Struct/AccountSettings.php` | 14 | 0 % | – | – |
| [ ] | `Component/Settings/Struct/ApplePaySettings.php` | 12 | 0 % | – | – |
| [ ] | `Component/Settings/Struct/PayPalExpressSettings.php` | 9 | 0 % | – | – |
| [x] | `Component/Settings/Struct/LoggerSettings.php` | 6 | 100 % | – | – |
| [ ] | `Component/Settings/Struct/CreditCardSettings.php` | 4 | 0 % | – | – |
| [x] | `Component/Settings/Struct/EnvironmentSettings.php` | 3 | 100 % | – | – |
| [ ] | `Component/Settings/AbstractSettingsService.php` | 0 | 0 % | – | – |

## Integration Tests

Candidates: settings services that persist through `SystemConfigService`
(DAL-backed) or resolve payment methods from the DAL.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- Concrete settings services extending `AbstractSettingsService` — one
  integration test per service validating read/write round-trip through
  `system_config`.
- Any settings service that resolves shipping or payment methods from the
  DAL.

Unit only: DTOs, exceptions, enums, the abstract base class itself.

Reference: existing `tests/Integration/Settings/*Test.php` — extend the same
pattern for new settings services.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | _(to be filled per wave)_ | – | – | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
