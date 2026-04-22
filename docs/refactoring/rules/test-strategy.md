# Test Strategy

This document describes the overall test strategy for the `shopware/` refactor.
Three test levels coexist in this repository — each has a well-defined purpose
and they must not blur together.

## Done criteria for the refactor

The refactor is **complete** when both of the following are true:

- `src/` no longer exists (every remaining class has been migrated, dropped or
  inlined elsewhere).
- `tests/PHPUnit/` no longer exists (every legacy test is either replaced by a
  new `tests/Unit/` or `tests/Integration/` test, or dropped together with the
  class it covered).

Until both folders are gone, the refactor is **in progress**. The route
inventory under `src/Resources/config/routes/` is the fastest visible
indicator (see [`index.md`](../index.md#migration-status-via-routes)).

---

## The three levels

| Level | Folder | Purpose | Dependencies | Data |
|---|---|---|---|---|
| Unit | `tests/Unit/` | Verify a single class in isolation | Fakes and Builders only, no container, no DB, no network | In-memory objects |
| Integration | `tests/Integration/` | Verify a single service against real infrastructure | Real Shopware kernel + DAL, real Mollie sandbox for gateway classes | Fixture classes |
| Behat | `tests/Behat/` | Verify a user flow spanning multiple services | Reuses Integration `TestBehaviour` traits, real Shopware kernel + DAL, real Mollie sandbox | Fixture classes, plus `Storage` for cross-step state |

### Unit tests

- **What:** every class in `Mollie\Shopware\*` (see exclusions in
  [`unit-testing.md`](unit-testing.md)).
- **How:** Fakes instead of mocks, Builders for complex data, `#[CoversClass]`
  on every test class, one test class per production class.
- **Why:** fast feedback, refactoring safety, type contracts, branch coverage.

Details: [`unit-testing.md`](unit-testing.md).

### Integration tests

- **What:** services that talk to real infrastructure — primarily the Shopware
  DAL (repositories, persistence) and the Mollie API (gateways, clients).
- **How:** extend `Shopware\Core\Framework\Test\TestCaseBase` using
  `IntegrationTestBehaviour`, load DB data through `Fixture` classes under
  `shopware/Component/Fixture/`, use the existing `MolliePage` helper to drive
  the real Mollie sandbox.
- **Why:** unit tests can't prove the DAL schema matches the code, and they
  can't prove the Mollie payload is accepted by Mollie. Integration tests can.

Details: [`integration-testing.md`](integration-testing.md).

### Behat tests

- **What:** user-level flows that exercise several services in sequence —
  login → add to cart → checkout → payment, or subscription creation →
  renewal → cancellation.
- **How:** Gherkin features under `tests/Behat/Features/`, Contexts under
  `tests/Behat/Context/`. Contexts reuse the Integration `TestBehaviour`
  traits directly (`use CheckoutTestBehaviour;`) so helpers exist in one place.
  Cross-step state lives in `tests/Behat/Storage.php`.
- **Why:** asserting a single service passes is not enough when the value is
  in the combination (a checkout that reaches Mollie, returns, triggers the
  webhook, and lands in the right order state).

Details: [`behat-testing.md`](behat-testing.md).

---

## When to pick which

Decision tree when adding tests for a new or migrated class:

1. Does the class touch external infrastructure?
   - **No** → Unit test is enough.
   - **Yes** → continue.
2. Does the feature value come from one service or from a chain?
   - **One service** (e.g. "is the Mollie payload correct", "does the
     repository write the right fields") → Integration test.
   - **Chain of services** (e.g. "checkout → Mollie → webhook → order state")
     → Behat test, reusing Integration traits.
3. Is the behaviour visible only through the combination?
   - **Yes** → Behat test.

Rules of thumb:

- A route or controller is unit-tested with a fake request/context. If it
  actually needs to talk to the DB, something else is probably under test as
  well — move that logic into a service and integration-test *that*.
- A service with a DAL repository gets an integration test. The unit test of
  the same service covers branching logic with a fake repository.
- A service that calls the Mollie client gets an integration test against the
  sandbox. The unit test covers payload assembly with a fake gateway.

---

## What each level does **not** do

- Unit tests do not boot the Shopware kernel, touch the DB, hit the network,
  or load YAML/XML config.
- Integration tests do not chain several unrelated services. One service,
  one conversation with the outside world.
- Behat tests do not re-prove behaviour that is already covered by a unit or
  integration test. They only add value through the end-to-end flow.

---

## Coupling to the refactor

The test strategy drives what we migrate *and* what we drop:

- If a class from `src/` has no migration target (dropped as overengineered),
  it also has no tests — delete the legacy test under `tests/PHPUnit/` along
  with it.
- If a class is migrated, it gets a unit test, and if it uses DAL or Mollie,
  additionally an integration test. Package files under
  [`../packages/`](../packages/) list both requirements per file.
- Behat requirements are global (per feature, not per class) and documented
  in [`behat-testing.md`](behat-testing.md).

---

## References

- [`unit-testing.md`](unit-testing.md) — unit test rules
- [`integration-testing.md`](integration-testing.md) — integration test rules
- [`behat-testing.md`](behat-testing.md) — Behat rules
- [`../index.md`](../index.md) — refactor overview, route status, coverage
- [`../../testing-guidelines.md`](../../testing-guidelines.md) — general
  project testing rules
- [`../../coding-guidelines.md`](../../coding-guidelines.md) — coding rules
