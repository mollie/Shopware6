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