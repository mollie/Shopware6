# Integration Test Rules for `shopware/`

Binding for every test under `tests/Integration/`. Goal: prove that services
which depend on external infrastructure (Shopware DAL, Mollie API) actually
work against that infrastructure.

These rules extend [`test-strategy.md`](test-strategy.md),
[`../../testing-guidelines.md`](../../testing-guidelines.md) and
[`../../coding-guidelines.md`](../../coding-guidelines.md).

---

## 1. Scope — what gets an integration test?

A class needs an integration test if **at least one** of these is true:

- It uses a Shopware DAL repository (`EntityRepository`, `$this->getContainer()->get('*.repository')`).
- It calls the Mollie client / gateway (`MollieApiClient`, `MollieGateway*`,
  anything that opens an HTTP connection to Mollie).
- It depends on the Shopware kernel for a service that cannot be meaningfully
  faked at unit level (e.g. the CartService, StateMachineRegistry, OrderConverter).

A class does **not** get an integration test if:

- It is a pure value object, DTO, exception, event, or struct
  (→ unit test only).
- It is a subscriber or scheduled task (→ unit test only — Behat covers the
  event chain when that matters).
- It is a route or controller that delegates to a service (→ unit test for
  the controller, integration test for the service it calls).
- It is in the excluded coverage scope (`Fixture`, `TranslationImporter`,
  `snippet`, `Migration`, `polyfill`).

### Fixture classes are test infrastructure

`shopware/Component/Fixture/*` is **not** in the coverage scope — we don't
write tests for fixtures themselves. But every integration test that needs DB
state **must** go through these fixture classes. They are the only sanctioned
way to insert test data.

---

## 2. File layout

```
tests/Integration/
├── Data/                     # Reusable TestBehaviour traits (Order, Customer, Checkout, …)
├── MolliePage/               # Real Mollie sandbox client (Guzzle, parses HTML forms)
├── Repository/               # Integration tests for repositories under shopware/
├── Settings/                 # Integration tests for settings services
├── <Component>/              # Integration tests grouped by Mollie\Shopware\Component\<X>
│   ├── <Feature>/
│   └── ...
```

A new integration test for a service in `Mollie\Shopware\Component\Payment\…`
goes to `tests/Integration/Payment/…` mirroring the production structure. If
no folder exists yet, create it.

### TestBehaviour traits

Reusable helpers live under `tests/Integration/Data/` as traits:

- `CheckoutTestBehaviour`
- `CustomerTestBehaviour`
- `OrderTestBehaviour`
- `PaymentMethodTestBehaviour`
- `ProductTestBehaviour`
- `RequestTestBehaviour`
- `SalesChannelTestBehaviour`
- `SubscriptionTestBehaviour`

Rules:

- A trait bundles helpers that need the Shopware container (e.g.
  `createOrder()`, `createCustomer()`).
- Traits **only** access the container through `$this->getContainer()` — never
  as a field or singleton.
- Traits must be usable from both PHPUnit Integration tests and from Behat
  Contexts. Avoid PHPUnit-specific assertions (`assertEquals`, etc.) inside
  trait helpers — return the object, let the test assert.
- When an Integration test starts to duplicate setup that another test
  already does, **extract** a new trait under `Data/` before copy-pasting.

---

## 3. Test class layout

```php
<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Payment;

use Mollie\Shopware\Integration\Data\OrderTestBehaviour;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

final class CreatePaymentTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderTestBehaviour;
    use PaymentMethodTestBehaviour;

    public function testCreatePaymentPersistsTransaction(): void
    {
        // arrange — via fixtures + TestBehaviour helpers
        // act — call the real service, resolved through the container
        // assert — query the real repository
    }
}
```

Mandatory:

- `declare(strict_types=1);`
- `final class`
- Extends `PHPUnit\Framework\TestCase` (**not** Shopware's `KernelTestCase`).
- `use IntegrationTestBehaviour;` provides kernel + container + DB
  transaction rollback between tests.
- Namespace: `Mollie\Shopware\Integration\<Component>\*`.
- Filename: `<ClassName>Test.php` mirroring the production class name.

### No `#[CoversClass]`

Integration tests intentionally cover more than one class (the service +
everything Shopware puts behind the repository). We do **not** add
`#[CoversClass]` here — the coverage number from integration tests is not
part of the ratchet. Unit tests drive the coverage metric.

---

## 4. Test data — fixtures only

**Strict rule:** every row that an integration test needs in the database
goes through a class under `shopware/Component/Fixture/` (extending
`AbstractFixture`).

No exceptions:

- No raw SQL `INSERT` from inside a test.
- No `$repository->create([...])` in a test file (that belongs in a
  fixture or a TestBehaviour trait, never in the test itself).
- No YAML/JSON data files read from the test. Data is code: a fixture class.

Why:

- Fixture classes are reused across Unit (as seeded-in-memory data via
  the Builder equivalent), Integration, and Behat. One source of truth.
- The `FixtureCommand` lets us reproduce a broken scenario locally with a
  single CLI call.

When a new kind of fixture is needed (a new product variant, a new voucher
type), extend the existing group (`shopware/Component/Fixture/Product/`,
`.../Voucher/` …) or add a new group under `shopware/Component/Fixture/`.

---

## 5. Mollie — real sandbox, never a mock

Integration tests that exercise Mollie must hit the **real Mollie sandbox**
(same environment the manual QA uses).

The helper for this is `tests/Integration/MolliePage/MolliePage.php` — a
Guzzle-based client that:

- Opens the Mollie checkout URL returned by the plugin.
- Parses the HTML form Mollie renders.
- Submits a chosen payment state (paid, failed, expired, canceled, …).
- Returns the redirect URL plus any parsed response data.

Rules:

- Use `MolliePage` whenever a test walks through the Mollie redirect flow.
- Do **not** add a second Mollie HTTP client — extend `MolliePage` instead.
- Sandbox credentials come from the test environment
  (`MOLLIE_API_KEY_TEST` / equivalent). Never commit real keys.
- If Mollie sandbox is unavailable, the test is allowed to fail — that is a
  real integration signal. Do not silently skip.

---

## 6. Cleanup between tests

`IntegrationTestBehaviour` wraps each test in a DB transaction that is rolled
back at teardown, so fixture data does not leak between tests.

- Do **not** add manual `truncate` / `delete` calls.
- Do not disable the transaction behaviour ("because my test needs to commit")
  — if you need committed data, that is a Behat scenario, not an integration
  test.
- Tests that spawn background workers or touch the message queue must drain
  the queue before asserting. See existing examples in
  `tests/Integration/Repository/` for the pattern.

---

## 7. No mocks

Same rule as unit tests: **no** `createMock()`, no `getMockBuilder()`. If a
collaborator can't be used in its real form during integration (e.g. an
external service we cannot call), that is a sign the test belongs one level
lower — use a fake at unit level instead.

The only "fake" external system accepted at integration level is the Mollie
**sandbox**, which is real software, just a separate environment.

---

## 8. Tracking

Each package file under [`../packages/`](../packages/) carries an
**Integration Tests** section listing the services in that package that need
integration coverage (filtered: DAL users + Mollie gateways + clients). The
same checkbox legend applies:

- `[ ]` no test
- `[/]` test exists, partial coverage
- `[x]` test covers the relevant flows
- `[~]` to-be-deleted (class is being dropped)

When a PR adds an integration test, update the corresponding row (test file
path, PR link, status). No separate coverage baseline file — the package
markdowns are the authoritative list.

---

## 9. Checklist per integration test PR

- [ ] Test class at `tests/Integration/<Component>/<ClassName>Test.php`.
- [ ] Namespace `Mollie\Shopware\Integration\<Component>\*`.
- [ ] `declare(strict_types=1);`, `final class`.
- [ ] Extends `TestCase`, `use IntegrationTestBehaviour;`.
- [ ] Every DB row loaded via a class in `shopware/Component/Fixture/`.
- [ ] No `createMock()` / `getMockBuilder()`.
- [ ] No raw SQL, no direct `$repository->create([...])` inside the test.
- [ ] Mollie interaction routed through `MolliePage` (if applicable).
- [ ] Shared helpers extracted to a `TestBehaviour` trait under
      `tests/Integration/Data/` if reused.
- [ ] Corresponding row in the package file under
      [`../packages/`](../packages/) → "Integration Tests" section updated.
- [ ] `make phpunit-integration` (or the project equivalent) passes.

---

## 10. Anti-patterns

- **Mocking the Shopware container / repository.** If you need a mock at
  integration level, the test should be a unit test instead.
- **Seeding data with inline `$repository->create([...])`.** Put that into a
  fixture class.
- **Chaining several services through one integration test.** Split into a
  focused integration test + a Behat scenario.
- **`sleep()` to wait for a webhook / queue.** Drain the queue explicitly.
- **Skipping when sandbox is slow.** Integration tests are the place where
  that kind of slowness is supposed to surface.
- **Asserting on log output or side effects of another service.** Assert on
  the actual output of the service under test.
