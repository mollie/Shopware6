# Testing Guidelines

Read this when writing tests — after the implementation review has passed.
Review the result against `.agents/reviews/tests.md`.

## Layout

| Kind | Location | Runs |
|---|---|---|
| Unit | `tests/Unit/<Component>/` | always, no shop, no API |
| Integration | `tests/Integration/` | needs a shop; API tests need a Mollie key |
| Behat | `tests/Behat/` | needs a shop **and** a Mollie key |
| Cypress | `tests/Cypress/` | needs a running storefront |

Mirror the production path: `shopware/Component/Refund/RefundBuilder.php` →
`tests/Unit/Refund/RefundBuilderTest.php`.

## Rules

**No mocking framework.** No `createMock()`, no `getMockBuilder()`. Write or reuse a fake in
the component's `Fake/` folder (`tests/Unit/Refund/Fake/`, `tests/Unit/Payment/Fake/`, …).

**`#[CoversClass]` is mandatory** on unit tests, so coverage stays precise.

**Arrange / Act / Assert**, in that order, visibly separated. One behaviour per test.
Prefer several small tests over one that checks many things.

**Name the test after the behaviour**, so the name reads like a sentence and the failure
output alone says what broke: `testResponseNotSuccessWithoutCustomer`,
`testItemQuantityIsZero`. Not `testHandle`, not `testGetAmountReturnsFloat`.

**Test data: inline when it is readable, a builder when it is not.**
Simple values stay inline and explicit — a reader should see the number that produces the
expected result without following a helper. Reach for a builder when the object is a
Shopware entity with required associations, or when the same setup repeats across tests.
`tests/Unit/Builder/` and the per-component `Builder/` folders hold them; extend an existing
one rather than writing a second.

```php
$order = OrderBuilder::create()->withPayment('paid')->withAmount(100)->build();
```

*Why the split:* a builder with hidden defaults makes a money test unreadable — you cannot
see which amount caused the assertion; twelve inline setters are worse. Pick what reads.

**Data providers when they improve readability** — several inputs with the same expected
behaviour, or a set of edge cases. Each dataset gets a name that describes its scenario. If
the provider makes the test harder to follow, write separate tests instead.

**Deterministic and order-independent.** No reliance on existing shop or database state, no
dependence on another test having run, no real clock or unseeded randomness. Control time by
injecting it.

**No kernel boot in a unit test.** Use real value objects and entities where that is
simpler, but do not start Shopware to test a calculation.

**Tag API-free integration tests with `#[Group('core')]`.**

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('core')]
final class MyIntegrationTest extends TestCase
```

The Mollie API key is a GitHub secret that fork pull requests do not get. The PR pipeline
keys off its presence:

- **No key** → `PHPUNIT_GROUPS=core` and `RUN_BEHAT=false`: only API-free integration
  tests run, Behat is skipped.
- **Key present** → no group filter, `RUN_BEHAT=true`: everything runs, like nightly.

So an untagged API-free test silently never runs on a PR, and a tagged API test fails there.
Cypress is not gated this way — it always runs `@core` on PRs; the full suite is too slow.

**For a bug fix, write the failing test first** — or at least confirm the new test fails
without the fix. State which assertion carries the regression.

## Cypress

- Test titles start with the TestRail case id: `it('C266669: Route available ...', ...)`.
  164 of the 219 existing tests follow this; new tests automate a prepared TestRail case
  rather than inventing a flow.
- Keyword-driven: steps come from `tests/Cypress/cypress/support/actions/` and
  `scenarios/`, not from raw selectors in the spec. Also available there: `models/`,
  `repositories/`, `services/`.
- One user flow per `it`. Assert the high-value outcomes — order status, payment status, the
  key UI state — not technical details.
- **No arbitrary waits.** `cy.wait(1000)` is a finding; wait on observable state or a
  network alias. (There are still 12 numeric waits in the suite — do not add the 13th.)
- Build the state you need through scenarios or the API. Never rely on existing shop data.
- Keep version-specific branches inside an Action or helper, not scattered through specs.
- Push edge cases down to unit or integration tests. E2E stays short.

## Running them

You do not run them. See section 5 of `AGENTS.md`: name the relevant target
(`make phpunit`, `make phpintegration`, `make vitest`, `make behat`) and hand over.
