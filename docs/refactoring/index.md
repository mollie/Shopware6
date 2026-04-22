# Refactoring: `src/` → `shopware/`

This documentation tracks the ongoing migration of the plugin from the legacy
namespace `Kiener\MolliePayments` (folder `src/`) to the new namespace
`Mollie\Shopware` (folder `shopware/`). In parallel, the **Mollie Orders API**
is being replaced by the **Mollie Payments API**.

## Quick overview

- **Old:** `src/` — `Kiener\MolliePayments\*` — gradually emptied.
- **New:** `shopware/` — `Mollie\Shopware\*` — target state.
- Not every class will be migrated. Some will be **dropped entirely**
  (over-engineered / no longer needed after the Payments API switch).
- Minimum Shopware version is **6.5.10**, so no polyfills are required.

## Migration status via routes

Routes are the plugin's entry points, so migration progress is most visible
there. A route that is still defined under `src/Resources/config/routes/`
means the corresponding feature has **not** been migrated yet.

**Still in `src/Resources/config/routes/`:**

- `admin-api/`: `cancel-items.xml`, `config.xml`, `order.xml`, `refund.xml`,
  `shipping.xml`, `subscription.xml`, `support.xml`, `webhook.xml`
- `store-api/`: `config.xml`, `subscription.xml`
- `storefront/`: `account.xml`, `mollie_failure.xml`

**Already migrated:** routes in `shopware/` are auto-loaded via attribute
routing (`shopware/Resources/config/routes.xml` scans `Component/**/*Route.php`
and `*Controller.php`). Anything no longer listed in
`src/Resources/config/routes/` has either been migrated or deleted.

## Test coverage baseline (as of 2026-04-22)

Only the namespace `Mollie\Shopware\*` is measured. **Excluded** from scope:

- `shopware/Component/Fixture/*` — internal dev tool
- `shopware/Component/TranslationImporter/*` — internal tool; snippet
  correctness is validated by external tooling
- `shopware/Resources/snippet/*`
- `shopware/Migration/*`
- `polyfill/*` — not needed since Shopware 6.5.10

| Metric | Value |
|---|---|
| Files in scope | 312 |
| Statements covered | 1068 / 5902 = **18.1 %** |
| Methods covered | 238 / 1280 = **18.6 %** |

Raw data: `Mollie_SW6_Unit_Tests_xml` (Clover).

## Coverage goals

Ratcheting and CI gates are explicitly **out of scope until ≥ 80 %** — we
want to establish the testing discipline before wiring up automation. Gates
land post-5.1.0 so the ratchet starts from a healthy number rather than
locking in the current 18.1 %. Progress is tracked per package (see below)
and reviewed per release (see [`release-plan.md`](release-plan.md)).

Targets align with the release plan:

| Checkpoint           | Target line coverage |
|----------------------|----------------------|
| Baseline 2026-04-22  | 18.1 %               |
| `5.0.0-beta.3`       | ~40 %                |
| `5.0.0-beta.4`       | ~50 %                |
| **`5.0.0` (End Q2 2026)** | **~60 %**        |
| `5.1.0` (End Q3 2026)    | **~80 %**        |

Targets are achieved through three waves:

1. **Wave 1 — low-hanging fruit (in progress):** entities, structs, DTOs,
   small exceptions, small events. Small tests, many files, quick jump to
   ~35 %.
2. **Wave 2 — business-critical:** payment actions, subscription actions,
   transaction, Mollie gateway extensions, routes with fake dependencies.
3. **Wave 3 — subscribers, controllers, express methods:** requires richer
   fakes (events, HTTP context).

## Release plan

The release schedule, per-version scope, and feature → version mapping
live in [`release-plan.md`](release-plan.md). **Hard deadline:
`5.0.0` ships by the end of Q2 2026.** Payment Links and the final
`src/` + `vendor_manual/` removal are deferred to `5.1.0` (end Q3 2026).

## Release features

Product-level features that have to land as part of this refactor live under
[`features/`](features/index.md) — one file per feature with status quo,
target state, phases, and open questions.

Currently drafted:

- [Multi-Subscription Checkout](features/subscriptions.md) — mixed carts,
  multiple subscriptions per order, per-interval renewal orders, price-update
  workflow.
- [Refunds on Payments API](features/refunds.md) — Payments-API refunds,
  legacy Orders-API fallback via stored Mollie ids, separate Mollie tab
  in the order detail, Vue decomposition, consolidated overview route.
- [Express Checkout — Address & Guest Account Sync](features/express-checkout-address-sync.md)
  — guest / address reuse parity between Apple Pay Direct and PayPal
  Express, ID-swap bug fix, missing tests.
- [PayPal Express — Temporary Orders-API Bridge](features/paypal-express-orders-api.md)
  — keep PayPal Express on the Orders API via a new
  `OrdersApiAwareInterface` marker until Mollie ships the missing
  Payments-API parameter.
- [Payment Links for Admin-Created Orders](features/payment-links.md)
  — let customers pay admin-created orders directly from the
  email via Mollie's Payment Links API; no shop login required.

## Rules

Three rule documents apply — one per test level:

- [`rules/test-strategy.md`](rules/test-strategy.md) — overall strategy,
  when to write Unit vs. Integration vs. Behat, done criteria for the refactor.
- [`rules/unit-testing.md`](rules/unit-testing.md) — unit test rules
  (fakes, builders, `#[CoversClass]`, layout).
- [`rules/integration-testing.md`](rules/integration-testing.md) — integration
  test rules (DAL, real Mollie sandbox via `MolliePage`, fixtures only,
  `IntegrationTestBehaviour` + `tests/Integration/Data/*TestBehaviour` traits).
- [`rules/behat-testing.md`](rules/behat-testing.md) — Behat as a multi-service
  extension of integration tests (reuses `TestBehaviour` traits, `Storage`
  pattern, Gherkin conventions).

### Done criteria for the refactor

The refactor is complete when both of these hold:

- `src/` no longer exists.
- `tests/PHPUnit/` no longer exists.

Until that moment, a legacy test under `tests/PHPUnit/` implies either a
class still in `src/` waiting to be migrated, or a test that needs a
replacement under `tests/Unit/`, `tests/Integration/` or `tests/Behat/`.

### Key points at a glance

- Fakes instead of mocks. Shared fakes in `tests/Unit/Fake/`,
  component-local fakes in `tests/Unit/<Component>/Fake/`.
- Builders in `tests/Unit/Builder/` (shared) or
  `tests/Unit/<Component>/Builder/` (component-local).
- Integration tests extend `TestCase` + `use IntegrationTestBehaviour;` and
  load DB data exclusively through `shopware/Component/Fixture/*`.
- Mollie is **never** mocked — the sandbox is the test double, accessed via
  `tests/Integration/MolliePage/MolliePage.php`.
- Behat Contexts reuse integration `TestBehaviour` traits directly; no
  duplicated helpers.
- One unit test class per production class, camelCase methods with `test`
  prefix, `#[CoversClass]` mandatory (unit only — integration tests do not
  use it).
- Classes under `Mollie\Shopware\*` are `final` with `declare(strict_types=1)`.
- A migration `src/` → `shopware/` is **not** automatically coupled to a
  new test — many classes will be removed instead. Tests are written only
  for classes that stay in the target state.

## Progress per package

One file per top-level component under `docs/refactoring/packages/`, listing
every file in scope with coverage status (checkbox), test file and PR. Each
package file also carries an **Integration Tests** section that names the
classes in the package that need integration coverage (DAL users +
Mollie-facing services).

**Components:**

- [Payment](packages/payment.md) — 134 files, 14.7 %
- [Subscription](packages/subscription.md) — 65 files, 7.9 %
- [Mollie](packages/mollie.md) — 40 files, 47.9 %
- [Shipment](packages/shipment.md) — 6 files, 0 %
- [Settings](packages/settings.md) — 13 files, 12.4 %
- [Transaction](packages/transaction.md) — 9 files, 0 %
- [Logger](packages/logger.md) — 7 files, 44.4 %
- [StateHandler](packages/statehandler.md) — 1 file, 95.3 %
- [FlowBuilder](packages/flowbuilder.md) — 16 files, 0 %
- [FailureMode](packages/failuremode.md) — 2 files, 0 %
- [Router](packages/router.md) — 1 file, 0 %
- [StatusUpdate](packages/statusupdate.md) — 4 files, 57.1 %

**Root-level under `shopware/`:**

- [Subscriber](packages/subscriber.md) — 7 files, 0 %
- [Entity](packages/entity.md) — 5 files, 0 %
- [Repository](packages/repository.md) — 1 file, 0 %

## References

- [`release-plan.md`](release-plan.md) — timeline, per-version scope,
  feature → version mapping, coverage targets per release
- [`docs/coding-guidelines.md`](../coding-guidelines.md) — `final`, strict
  types, DTOs, early returns
- [`docs/testing-guidelines.md`](../testing-guidelines.md) — fakes, builders,
  `#[CoversClass]`
- [`docs/index.md`](../index.md) — project structure
- `config/phpunit.xml` — PHPUnit unit configuration (testsuite "unit")
- `config/phpunit.integration.xml` — PHPUnit integration configuration
  (testsuite "integration", boots the Shopware kernel)
- `config/behat.yaml` — Behat configuration (Contexts under
  `Mollie\Shopware\Behat\Context`)
