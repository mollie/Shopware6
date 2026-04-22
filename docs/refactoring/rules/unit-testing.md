# Unit Test Rules for `shopware/`

Binding for every test under `tests/Unit/`. Goal: consistent, refactoring-safe
tests that raise coverage of the `Mollie\Shopware\*` namespace over time.

These rules extend the general
[`docs/testing-guidelines.md`](../../testing-guidelines.md) and
[`docs/coding-guidelines.md`](../../coding-guidelines.md).

---

## 1. Scope — what is tested?

**Every** production class in the `Mollie\Shopware\*` namespace is a test
candidate. This explicitly includes:

- Services, Actions, Builders, Factories
- Routes and Controllers (as unit tests with fake dependencies — **not**
  integration tests)
- Subscribers
- Entities, Structs, DTOs, Exceptions, Events

**Excluded from the coverage scope** (neither tested nor measured):

- `shopware/Component/Fixture/*` — internal developer tool
- `shopware/Component/TranslationImporter/*` — internal tool
- `shopware/Resources/snippet/*` — snippets are validated by external tooling
- `shopware/Migration/*` — DB migrations
- `polyfill/*` — no longer relevant since Shopware 6.5.10

### Classes scheduled for deletion

Not every class in `src/` will be migrated — some will be dropped entirely as
part of this refactor. For those classes:

- No tests are written.
- Mark them in the corresponding package file under
  `docs/refactoring/packages/` with status `[~]` and the note `to-be-deleted`.
- Once deleted, remove the row from the file.

---

## 2. File layout

```
tests/Unit/
├── Fake/                    # SHARED fakes (generic repositories, event dispatcher, …)
├── Builder/                 # SHARED builders (OrderBuilder, CustomerBuilder, …)
├── Payment/
│   ├── PayTest.php
│   ├── Fake/                # COMPONENT-LOCAL fakes (only if Payment-specific)
│   ├── Builder/             # COMPONENT-LOCAL builders (e.g. PaymentsApiPayloadBuilder)
│   └── Action/FinalizeTest.php
├── Subscription/
│   ├── Fake/
│   ├── Builder/
│   └── …
├── Mollie/
└── …
```

Two levels of shared-ness:

- **Shared across components** → goes to `tests/Unit/Fake/` or
  `tests/Unit/Builder/`.
  Examples: `FakeCustomerRepository`, `FakeEventDispatcher`, `OrderBuilder`,
  `CustomerBuilder`.
- **Component-local** → stays in `tests/Unit/<Component>/Fake/` or
  `tests/Unit/<Component>/Builder/`.
  Examples: `FakePaymentMethodHandler` (Payment-specific),
  `PaymentsApiPayloadBuilder` (Payment-specific).

**Rule of thumb:** if a fake/builder is used in **two or more** component
folders, move it up to the shared folder. If it's used in exactly one
component folder, keep it there.

### Known duplicate to clean up

At the start of this initiative the following duplicate exists (rebase
artefact):

- `tests/Unit/Fake/FakeCustomerRepository.php` — shared, **keep**
- `tests/Unit/Payment/Fake/FakeCustomerRepository.php` — **delete**, update
  imports in `PayTest.php` and `CreatePaymentBuilderTest.php` to use the
  shared one.

This is a one-off cleanup done in the first test PR.

---

## 3. Test class layout

```php
<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Action;

use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Unit\Fake\FakeEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pay::class)]
final class PayTest extends TestCase
{
    public function testPayRedirectsToMollieCheckoutUrl(): void
    {
        // arrange
        // act
        // assert
    }
}
```

Mandatory:

- `declare(strict_types=1);`
- `final class`
- `#[CoversClass(...)]` on the class
- Extends `PHPUnit\Framework\TestCase` (**not**
  `Shopware\Core\Framework\Test\TestCaseBase` — that would be integration).
- **One test class per production class**, filename `<ClassName>Test.php`.
  Exception: a production class with > 3 clearly distinct behaviour groups
  may be split into `Group1Test`, `Group2Test` etc.

### Method naming

- Camel case with `test` prefix: `testPayRedirectsToMollieCheckoutUrl()`.
- Reads like a sentence, describes **observed behaviour**, not implementation.
  - Good: `testCreatePaymentSendsMolliePaymentMethod`
  - Bad: `testCall1`, `testBuildMethod`
- For > 10 similar scenarios: use `#[DataProvider]` instead of copy/paste.

### Docblock (optional, recommended for tricky tests)

```php
/**
 * Verifies that when the Mollie checkout URL is empty, the Shopware return
 * URL is used instead (fallback path in finalizePayment).
 */
public function testPayRedirectsToShopwareReturnUrlWhenMollieUrlIsEmpty(): void
```

---

## 4. Fakes instead of mocks

**No** `$this->createMock()` or `$this->getMockBuilder()`. Use small fake
classes that implement the interface / extend a Shopware base class:

```php
final class FakeCustomerRepository extends EntityRepository
{
    public function __construct() {}

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }
}
```

Rules:

- Fakes are `final`.
- They carry their state explicitly (fields, not via reflection).
- For observable behaviour (e.g. "was `upsert()` called?"): a `$calls[]`
  field or similar, **not** PHPUnit's `InvocationMocker`.
- When a fake is used in ≥ 2 component folders, move it to
  `tests/Unit/Fake/`.

---

## 5. Builders for complex objects

Required by `docs/testing-guidelines.md` for complex test objects. Follow
the same shared/local split as fakes:

- **Shared builders** in `tests/Unit/Builder/`. Examples: `OrderBuilder`,
  `CustomerBuilder`, `CartBuilder`, `ContextBuilder`.
- **Component-local builders** in `tests/Unit/<Component>/Builder/`.
  Examples: `PaymentsApiPayloadBuilder` (Payment-specific),
  `SubscriptionPayloadBuilder` (Subscription-specific).

```php
final class OrderBuilder
{
    public static function create(): self { … }
    public function withPayment(string $state): self { … }
    public function withAmount(float $amount): self { … }
    public function build(): OrderEntity { … }
}
```

Rules:

- Fluent API, `create()` as entry point.
- `build()` returns the final object.
- No builder logic inside the test — just calls.
- When a builder is used in ≥ 2 component folders, move it to
  `tests/Unit/Builder/`.

---

## 6. Coverage tracking

There is currently **no automated coverage gate**. Progress is tracked
manually through the package files under `docs/refactoring/packages/`:

1. Each package file lists every production file in scope with its current
   coverage status (`[x]` / `[/]` / `[ ]` / `[~]`).
2. When a test PR adds or improves coverage for a file, the author updates
   the corresponding row (tick the checkbox, link the test file, link the PR).
3. `index.md` summarises total progress and may be updated occasionally.

No baseline file, no script, no CI gate. We may add ratcheting later, but
for now the goal is to establish the discipline first.

---

## 7. Checklist per test PR

Before a PR that adds tests for a class in the `Mollie\Shopware` namespace
is merged:

- [ ] Test class located at `tests/Unit/<Component>/<ClassName>Test.php`.
- [ ] Namespace `Mollie\Shopware\Unit\<Component>\*`.
- [ ] `declare(strict_types=1);`, `final class`, `#[CoversClass(...)]`.
- [ ] No mocks.
- [ ] Fakes used in multiple component folders moved to `tests/Unit/Fake/`.
- [ ] Builders used in multiple component folders moved to
      `tests/Unit/Builder/`.
- [ ] `make phpunit` passes.
- [ ] Corresponding row in the package file under
      `docs/refactoring/packages/<name>.md` updated (status, test file, PR).

---

## 8. Anti-patterns

- **Reflection on private methods.** If a private method deserves a test,
  it belongs in its own class.
- **Integration tests disguised as unit tests.** No `KernelBrowser`, no
  real DB, no `$this->getContainer()`. If you need those → `tests/Integration/`.
- **Fakes behaving like mocks.** A fake should show plausible behaviour on
  its own, not be reconfigured per test.
- **Copy-pasted test data.** Repeated object setup goes into a builder,
  repeated dependency setup goes into a private helper on the test class.
- **Getter spam.** An entity test that checks 30 getters one by one should
  be consolidated with `#[DataProvider]`.
