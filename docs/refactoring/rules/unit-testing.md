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
  Examples: `FakeCustomerRepository`, `EventSpy`, `OrderBuilder`,
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
use Mollie\Shopware\Unit\Fake\EventSpy;
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
  Exceptions:
  - A class with > 3 clearly distinct behaviour groups may be split into
    `Group1Test`, `Group2Test`.
  - A *family* of trivial sibling classes (e.g. all `Subscription*Event`
    subclasses, all `*Extension`s, all sprintf-only exceptions, all cart
    `Error` classes) may share one test file (`<Family>sTest.php`) using
    `#[DataProvider]` and a multi-`#[CoversClass(...)]` block. Use this
    when the bodies would otherwise be near-identical 5-line files.

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
- State fields are **private**. Expose observable behaviour through small
  spy methods like `getCallCount()`, `getCalls(string $method)`,
  `getLastUpsert()`. Tests should not poke at `$fake->calls[0]`.
- For multiple methods on one fake, prefer a generic `getCallCount(string $method)`
  / `getCalls(string $method)` over per-method spies — keeps the fake small
  and the tests symmetric.
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
- **Never inline a `Builder::create()->...->build()` call as a function
  argument.** Always assign to a named local first, then pass the variable.
  - Bad: `$context->setCustomer(CustomerBuilder::create()->asGuest()->build());`
  - Good:
    ```php
    $customer = CustomerBuilder::create()->asGuest()->build();
    $context->setCustomer($customer);
    ```
  Reason: when the test fails and you read it back, named locals make the
  test setup obvious at a glance — what is each role in this scenario?
  Inline chains hide that.
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

---

## 9. Patterns we landed on

Distilled from the Subscription package. Apply by default in new modules.

### 9.1 Refactor before testing

If a class becomes anemic after extracting Shopware-internal pipelines into
their own collaborator (e.g. `CartService`, `OrderConverter`, `EntityRepository`
chains), drop or split it instead of writing a unit test for what is now a
pass-through. Examples shipped with this package:

- `SubscriptionRemover` → carved out `SubscriptionLineItemsResolver` (cart
  vs. order loading, integration-only) so the remover unit-tests with a fake
  resolver.
- `SubscriptionAmountCalculator` → carved out `SubscriptionGroupCartBuilder`
  (cart pipeline). Calculator was then so thin it was deleted; callers use
  the builder + `SubscriptionGroupAmount` value object directly.

The extracted pipeline classes go on the integration-test list of the package
file with a one-line reason.

### 9.2 Value objects for duplicated logic

When the same algorithm shows up in production *and* in test setup, lift it
into a small `Stringable` / immutable VO. Production and test then share the
same source of truth.

- `SubscriptionAddressId` — UUID-from-fields, used by `SubscriptionAddressSyncer`
  and by the syncer's test for "is this id already there?".
- `SubscriptionGroupAmount` — `gross() / net() / getTaxStatus()` plus
  `fromGroupCart() / fromOrder() / fromGroupCartOrOrder()`, replaces
  hand-rolled fallback logic at every call site.

### 9.3 Native typed properties on entities

Migrated entities use native types, no `@var` annotations:

- non-nullable scalar → `protected string $name = '';`
- nullable scalar/object → `protected ?Type $name = null;`
- collections that read with a default-empty fallback →
  `protected ?CollectionType $items = null;` plus
  `return $this->items ?? new CollectionType();` in the getter.

Don't initialise object properties to `new SomeClass()` inline — PHP forbids
`new` in property defaults. Use the `?Type = null` + fallback-getter pattern.

### 9.4 Definition tests

For each `EntityDefinition`, one shared `EntityDefinitionsTest.php` with a
`#[DataProvider]` covers all definitions in the package. It checks four
things:

- `getEntityName() === ENTITY_NAME` (constant ↔ method consistency)
- `#[AutoconfigureTag('shopware.entity.definition', ['entity' => …])]` carries
  `ENTITY_NAME` (Reflection on attributes)
- `getEntityClass()` and `getCollectionClass()` return the expected classes
- Every non-runtime `Field` in `defineFields()` (excluding `id`, `createdAt`,
  `updatedAt`) has a matching `getX()`/`setX()` pair on the entity class
  (Reflection on the entity)

This is the static-analysis layer; `dal:validate` in CI is the dynamic layer
against the DB.

### 9.5 Context idioms

- Use `Context::createDefaultContext()` in unit tests, not
  `new Context(new SystemSource())`. Yes, the method is `@internal`, but
  Shopware's own UPGRADE doc explicitly carves out test usage.
- If a test uses the `Context` more than once, hold it in one local
  `$context = Context::createDefaultContext();` and pass it through helpers
  (`loadSubscriptionData($repository, $context)`). Don't re-create per call.
- For services that need a `SalesChannelContext`, use the shared
  `tests/Unit/Fake/FakeSalesChannelContext`. Internal fields are `$fakeXxx`
  to avoid clashing with private parent-class properties.

### 9.6 Trivial exception coverage piggybacks on service tests

For sprintf-only exceptions thrown by exactly one service/action, do not
write a dedicated `XxxExceptionTest.php`. Add `#[CoversClass(XxxException::class)]`
on the test of the throwing service. Coverage-tracking-wise that's enough;
behaviourally there is nothing to assert beyond "is thrown when …", which
the service test already proves.

### 9.7 DataProvider with closure factories

When a family of small classes needs different constructor arguments
(e.g. `new ErrorA()` vs. `new ErrorB($lineItemId)`, or static-factory chains
like `RenewException::invalidPaymentId(...)`), the data provider yields
`Closure` factories, not the instances themselves. The test invokes the
closure inside the test method:

```php
'invalid-payment-id' => [
    static fn (): RenewException => RenewException::invalidPaymentId('sub-1', 'pay-2'),
    Response::HTTP_UNPROCESSABLE_ENTITY,
    RenewException::INVALID_PAYMENT_ID,
    ['sub-1', 'pay-2'],
],
```

Keeps the data provider declarative and avoids pre-instantiation.

### 9.8 Pre-populating Shopware `Collection`s in tests: use `add()`, not the constructor

`new ErrorCollection([$a, $b])` keys entries with numeric indices because
`Collection::__construct` calls `set($key, $element)` with the iteration
key. Custom keying (e.g. `ErrorCollection::add` keying by `$error->getId()`)
is only applied through `add()`. So when seeding cart errors in a test:

```php
// Wrong — keys become 0, 1; remove(KEY) won't find anything
$cart->setErrors(new ErrorCollection([new InvalidGuestAccountError()]));

// Right — keys via getId()
$cart->getErrors()->add(new InvalidGuestAccountError());
```

This applies to any Shopware `Collection` whose `add()` overrides the
keying. In production code Shopware always goes through `add()`, so this
is purely a test-setup pitfall.

### 9.9 `\DateTimeInterface`, not `\DateTime`, on the production side

Twice we found production code that worked for `\DateTime` but silently
broke for `\DateTimeImmutable` because of `clone + ->modify(...)` without
the result being reassigned. The safe pattern is:

```php
$copy = clone $original;
$copy = $copy->modify('-5 day');
```

…and `instanceof \DateTimeInterface` rather than `\DateTime` on the receiving
side. When testing renewal/cancel windows, alternate between
`\DateTime` and `\DateTimeImmutable` in the inputs to catch this.
