# Testing Guidelines

These rules apply to all tests in `/tests`.

---

## Avoid PHPUnit Mocking Framework
Do not use `$this->createMock()` or `$this->getMockBuilder()`.  
Instead, create simple fake classes or reuse existing fake classes.

Reason: Fakes make behavior explicit, mocks hide behavior.

---

## Use Builder Classes for Complex Objects
For complex test objects, use Builder classes to configure them.

Example:

```php
$order = OrderBuilder::create()
    ->withPayment('paid')
    ->withAmount(100)
    ->build();
```

Reason: Improves readability and avoids test duplication.

---

## #[CoversClass] Required
Each unit test must declare coverage explicitly.

Example:

```php
#[CoversClass(PaymentService::class)]
```

Reason: Ensures precise code coverage and prevents accidental over-coverage.

---

## Tag API-free integration tests with `#[Group('core')]`
Pull requests run without a Mollie API key (the key is a GitHub secret only available
on the main project, not on forks/PRs). Integration tests that do **not** hit the Mollie
API must be tagged:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('core')]
final class MyIntegrationTest extends TestCase
```

Tests that call the Mollie API (e.g. `startCheckout()`, the gateway, a raw client) stay
**untagged**. For PHPUnit and Behat the PR pipeline keys off whether the
`MOLLIE_APIKEY_TEST` secret is present:

- **No key** (fork PRs): `PHPUNIT_GROUPS=core` + `RUN_BEHAT=false`, so only API-free
  integration tests run and Behat is skipped.
- **Key present** (e.g. a maintainer's own PR): no group filter + `RUN_BEHAT=true` — all
  integration tests and Behat run, same as nightly/CI.

Behat is API-dependent as a whole, hence the `RUN_BEHAT` switch in `run-e2e`. Cypress is
**not** gated this way — it always runs the `@core` filter on PRs because the full Cypress
suite takes too long.

Reason: A new API test left untagged simply won't run on PRs instead of failing there.
But a new **core** test left untagged also won't run on PRs — so tag every API-free
integration test.