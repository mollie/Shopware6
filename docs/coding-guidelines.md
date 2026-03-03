# Coding Guidelines

These rules apply to all new code inside `/shopware`.

---

## Strict Types
Every PHP file must start with:

```php
declare(strict_types=1);
```

Reason: Type safety and predictable behavior.

---

## Classes Must Be Final
All classes must be declared as `final`.  
Extensions must be intentional and explicit.

- Final classes require an interface for mocking.
- Abstract classes do **not** require an interface.

---

## Use Early Returns
Avoid nested conditions. Return early instead.

Bad example:

```php
if ($condition) {
    // ...
}
```

Preferred:

```php
if (!$condition) {
    return;
}
```

Reason: Improves readability and reduces cognitive load.

---

## Avoid Void Return Types
Avoid `void` return types whenever possible.  
Return a result object or response object instead.

Reason:
- Improves testability
- Makes behavior explicit
- Enables richer assertions in unit tests

---

## Avoid Arrays as Data Structures
Do not use associative arrays as structured data.  
Use DTOs or Plain PHP Objects instead.

Bad:

```php
return ['amount' => 100, 'currency' => 'EUR'];
```

Preferred:

```php
return new PaymentAmount(100, 'EUR');
```

Reason: Type safety, self-documenting code, better refactoring support.

---

## Prefer Reuse Over New Code
Before creating a new class, check existing:

- Services
- DTOs
- Utilities

Rule: Less code is better than more code. Avoid duplication.