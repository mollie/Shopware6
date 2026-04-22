# Behat Test Rules

Binding for every feature under `tests/Behat/`. Behat is positioned in this
project as an **extension of integration tests**: it reuses the same
`TestBehaviour` traits and fixture mechanism, but strings several services
together into a user-level flow.

These rules extend [`test-strategy.md`](test-strategy.md) and
[`integration-testing.md`](integration-testing.md).

---

## 1. When to write Behat, not Integration

Use Behat when the assertion only makes sense across **more than one
service**. Typical shapes:

- **Checkout flow:** login → add product → checkout → Mollie redirect →
  webhook → order state.
- **Subscription lifecycle:** create subscription → first payment → renewal
  → cancellation.
- **Shipment flow:** complete order → ship → Mollie shipment call → state
  update.

Use Integration (not Behat) when:

- A single service is under test, even if it uses DAL or Mollie.
- The scenario does not require state carried across steps.

Behat is not a replacement for integration tests. If a flow has five steps
and four of them are already covered by integration tests, the Behat feature
only needs to prove that the **combination** works end to end.

---

## 2. File layout

```
tests/Behat/
├── Context/                  # Contexts — reuse Integration TestBehaviour traits
│   ├── BootstrapContext.php  # Boots kernel, resets Storage between scenarios
│   ├── ShopwareContext.php   # Base class — holds shared setup
│   ├── CheckoutContext.php
│   ├── CustomerContext.php
│   ├── PaymentContext.php
│   └── SubscriptionContext.php
├── Features/                 # Gherkin .feature files
│   ├── payment.feature
│   ├── shipment.feature
│   └── subscription.feature
└── Storage.php               # Cross-step state holder (order id, payment id, …)
```

Rules:

- One context per domain concept (Checkout, Payment, Subscription, Customer).
  Contexts must not grow into a god-object.
- A feature may use multiple contexts — that's the whole point of Behat.
- New contexts are registered in `config/behat.yaml` under
  `Mollie\Shopware\Behat\Context`.

---

## 3. Context layout

```php
<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Mollie\Shopware\Integration\Data\CheckoutTestBehaviour;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;

final class CheckoutContext extends ShopwareContext
{
    use CheckoutTestBehaviour;
    use PaymentMethodTestBehaviour;

    /**
     * @When /^I checkout with (.*)$/
     */
    public function iCheckoutWith(string $paymentMethod): void
    {
        // reuse CheckoutTestBehaviour helpers
        // write the resulting order id into Storage
    }
}
```

Mandatory:

- `declare(strict_types=1);`, `final class` (unless extending another
  context), extends `ShopwareContext`.
- Reuses `tests/Integration/Data/*TestBehaviour` traits — **no duplicate
  helpers**.
- Methods are thin: they delegate to trait helpers, then push/pull state
  from `Storage`.

### Storage — cross-step state

Behat scenarios cross step boundaries: step 1 creates an order, step 5
asserts on it. That state lives in `tests/Behat/Storage.php` (a simple
key-value holder).

Rules:

- `BootstrapContext` clears `Storage` at the start of every scenario.
- Keys in `Storage` are **stable, documented strings** (`order_id`,
  `transaction_id`, `subscription_id`). Add new keys sparingly; duplicates
  across contexts cause silent collisions.
- `Storage` is **not** a god-object for arbitrary data — it only holds IDs /
  references that the next step needs.

---

## 4. Gherkin conventions

- `.feature` files under `tests/Behat/Features/`.
- One `Feature:` per file, matching the filename.
- Prefer **`Scenario Outline`** for repeated flows (e.g. the same checkout
  across all payment methods — see `payment.feature`).
- Step names are **business-readable**, not technical. Good:
  `When I pay with iDEAL`. Bad: `When the CheckoutService is called with
  payment_method_id=<uuid>`.
- A step should be **idempotent** if the scenario allows retry, otherwise
  explicit.

### Good example

```gherkin
Feature: Payment with Mollie

  Scenario Outline: Customer completes a checkout with a Mollie payment method
    Given a registered customer
    And a product in the cart
    When I checkout with <paymentMethod>
    And I complete the Mollie payment as <state>
    Then the order state is <orderState>

    Examples:
      | paymentMethod | state  | orderState |
      | iDEAL         | paid   | paid       |
      | Klarna        | paid   | paid       |
      | iDEAL         | failed | failed     |
```

### Bad patterns (to avoid)

- Huge scenarios that re-test single-service behaviour.
- Steps that assert implementation details (`Then CreatePaymentBuilder was
  called with …`) — that belongs in a unit test.
- Writing `Scenario Outline` with 3 examples when a plain `Scenario` is
  clearer.

---

## 5. Fixtures and data

Same rule as integration tests: **all** database state comes from
`shopware/Component/Fixture/` classes. Contexts call fixtures via
`TestBehaviour` traits or directly through the container.

- No inline repository writes inside a context method.
- Every new feature ships with the fixtures it needs — if the fixture
  doesn't exist yet, add it under `shopware/Component/Fixture/` first.

---

## 6. Mollie — same sandbox

Behat scenarios drive the **real Mollie sandbox** through the same
`MolliePage` helper used by integration tests. No mock, no stub, no wiremock.

- `MolliePage` can be reused inside a context (`use MolliePage;` as a
  dependency, not a trait — it's a plain class).
- If a Mollie step needs a new state (e.g. "pending"), extend `MolliePage`,
  don't branch inside the context.

---

## 7. Reuse over duplication

Whenever you're about to write a helper inside a Behat context:

1. Check if a matching trait already exists under `tests/Integration/Data/`.
2. If yes, `use` it.
3. If no, but the helper will also be useful from an integration test, add
   it as a new trait under `tests/Integration/Data/`, then `use` it.
4. Only Behat-only helpers (driving Gherkin-step state, `Storage`
   manipulation) live inside the context.

Pattern confirmed by the existing code — `CheckoutContext` already uses
`CheckoutTestBehaviour` and `PaymentMethodTestBehaviour`. Stay on that path.

---

## 8. Running

Behat runs against the same test kernel as integration tests. The normal
flow is:

1. Start the test environment.
2. Run `vendor/bin/behat` (or the project wrapper target).
3. Failures show the Gherkin step that failed plus the full PHP trace.

Behat is **not** part of unit or integration CI — it runs as its own job.
The done criterion from [`test-strategy.md`](test-strategy.md) applies here
too: every removed `tests/PHPUnit/` scenario that represented a multi-service
flow needs a Behat replacement before it can be deleted.

---

## 9. Checklist per Behat PR

- [ ] Feature file named after the feature (`<feature>.feature`).
- [ ] At least one `Scenario` or `Scenario Outline` per new behaviour.
- [ ] Steps are business-readable, not implementation-coupled.
- [ ] Context class is `final`, extends `ShopwareContext`,
      `declare(strict_types=1);`.
- [ ] Helpers reused from `tests/Integration/Data/*TestBehaviour` traits, no
      duplication.
- [ ] Fixtures added under `shopware/Component/Fixture/` if new data is
      needed.
- [ ] Mollie interaction uses `MolliePage` — no new HTTP client.
- [ ] `Storage` keys documented, no collisions.
- [ ] Context registered in `config/behat.yaml` if new.

---

## 10. Anti-patterns

- **Behat as a second integration test for a single service.** Push it down
  to `tests/Integration/`.
- **Copy-pasted helper methods.** Extract to `tests/Integration/Data/`.
- **Gherkin that reads like code.** Rewrite in business terms.
- **Storage as a grab bag.** Only IDs/references that the next step needs.
- **Mocked Mollie.** We never mock Mollie — the sandbox is the test double.
- **Per-scenario database reset via raw SQL.** `IntegrationTestBehaviour`
  transactions + fixtures handle this.
