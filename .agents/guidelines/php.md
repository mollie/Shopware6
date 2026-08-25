# PHP Guidelines — Senior Review Standard

Applies to all PHP in `shopware/`, when writing code and when reviewing it.
`AGENTS.md` section 7 carries the short version; this file is the full standard and the
basis for `.agents/skills/review-conventions/SKILL.md`.

State the consequence in a review, not just the rule name.

---

## Control flow

**Early returns. No `else`, no `elseif`.**
Invert the condition and return. The happy path stays at the lowest indentation level.

**No nested conditionals.**
Use guard clauses, `continue` inside loops, or extract a private method.

The codebase has ~16 `else` branches across 568 files — do not add to that number.

**No computed list in a loop header.** A call that *builds* the list — `explode()`,
`array_merge()`, `array_filter()`, `json_decode()` — is assigned to a named variable first:
`$parts = explode(',', $list);` before `foreach ($parts as $part)`. A plain accessor stays
where it is; the codebase has ~55 headers of the form `foreach ($order->getLineItems() as
$lineItem)` and they are fine.
*Why:* the header should name what is being iterated, not compute it.

**No arrow functions.**
Write a normal closure with a body and an explicit `return`, never `fn () =>` or
`static fn () =>`.
*Why:* an arrow function captures the whole scope implicitly and has to be rewritten as soon
as it needs a second statement.

---

## Types and data

**Strong typing everywhere.** Parameters, returns, properties. No `mixed`, no untyped
`array` crossing a class boundary. The floor is the `composer.json` constraint, PHP 8.2:
enums, `readonly`, union, intersection and DNF types are all available and a native union
beats a wide type plus a `@param` docblock. Typed class constants (8.3) and property hooks
(8.4) are not available.

**Avoid `null`. Use a sensible default.**
An empty string, an empty collection, a zero amount, a null-object. Where absence is
genuinely part of the domain, model it explicitly rather than leaking `?Foo` through
several layers.

**Never `empty()`.**
Use `count($collection) === 0` for collections and `strlen($string) === 0` for strings.
*Why:* `empty()` treats `'0'`, `0`, `0.0`, `[]`, `null` and `false` alike — in a payment
plugin that silently swallows a legitimate zero amount.

**No `void` returns.**
Return the result, a DTO, or a Result object.
*Why:* a `void` method can only be tested through its side effects.

**No raw arrays as data structures.**
DTOs, Value Objects and Collections instead. `new PaymentAmount(100, 'EUR')`, never
`['amount' => 100, 'currency' => 'EUR']`.

**Dedicated Collection classes instead of array-function chains.**
An `array_filter(array_map(...))` chain over domain objects belongs behind a named method
on a Collection. The codebase has the pattern already — `LineItemCollection`,
`RefundCollection`, `PaymentCollection`, `AttachmentCollection`,
`VisibilityRestrictionCollection`.

**No magic values.** Extract a constant, or better a Value Object.

---

## Classes and dependencies

**`final` by default.** Inheritance is a deliberate decision, not the default.

**An injected dependency needs an interface.**
Any class that is constructor-injected into another class gets an interface, so it can be
substituted by a Fake in tests. This is what the codebase does — 47 interfaces, sitting
exactly on the injected services.

Does *not* apply to: Structs/DTOs, Collections, Entities/Definitions, Controllers, Routes,
Subscribers, scheduled-task handlers, Migrations. They are not substituted.
*Why:* for substitution, not symmetry — one implementation and no double is dead weight.

**Small constructors, few arguments.**
A constructor grown past a handful of dependencies is doing more than one job. Extract the
responsibility into its own service rather than adding another dependency.

**One responsibility per service.** If the class name needs an "And" to be accurate, split
it.

**Optional and defaulted arguments last.** Always.

**`Psr\Log\LoggerInterface` is the last constructor dependency.** In this project it is
also always wired explicitly:

```php
public function __construct(
    private readonly RefundBuilderInterface $refundBuilder,
    #[Autowire(service: 'monolog.logger.mollie')]
    private readonly LoggerInterface $logger,
) {
}
```

*Why:* without the `#[Autowire]` the log lands in Shopware's channel instead of Mollie's.

**Constructor injection only.** No service locator, no `$container->get()`, no static
accessor reaching for a service.

**Pass `Context` and `SalesChannelContext` through.** Never rebuild one further down, and
never fall back to a default context to make a call work.
*Why:* a rebuilt context silently reads the wrong version or language.

**Do not depend on Shopware internals.** No class or service marked `@internal`, no
`private` service id, no copy of a core class. If the only way in is an internal API, say so
instead of doing it quietly.
*Why:* internals change in minor releases — the top cause of a plugin breaking on update.

**DRY.** Search before you add. Reuse an existing service, struct or helper even when your
own version would be slightly nicer.

---

## Naming and intent

**Descriptive names, no abbreviations.** `$paymentTransaction`, not `$pt`. `$index`, not
`$i`, outside a trivial loop.

**Comments only where the code cannot carry the intent, three lines at the most.**
Delete any comment that restates the next line. Keep — and *write* — the comment that
records a reason the code cannot show: a Mollie API constraint, a rounding rule, a
Shopware-version workaround, a deliberate rule violation. The ceiling holds for a class
docblock too, and it holds for a public extension point: a docblock that explains how to use
the class — when to call it, what a listener may or may not do — is documentation, and
documentation does not go in the source. Beyond that only the `@param`, `@return` and `@var`
PHPStan cannot infer.

---

## Errors

**Domain-specific exceptions.** No bare `\Exception`, `\RuntimeException` or
`\InvalidArgumentException`. The codebase groups them per component in
`shopware/Component/<Domain>/Exception/` — `ApiKeyException`, `MissingCountryException`,
`TransactionWithoutMollieDataException`. Add to that set.

**Do not swallow failures on a payment path.** An exception that is caught, logged and
ignored where money is involved is a defect, not defensiveness.

---

## Performance

**No SQL or DAL query inside a loop.** Collect the criteria, run one query, index the
result.

**No API call inside a loop.** Batch it, or restructure so one call answers for all items.

*Why (both):* they scale with cart or order size — they pass every test and fail in production.

---

## Tests

Fakes, Spies and Builders, no mocking framework. Details in `.agents/guidelines/testing.md`.

---

## When rules conflict

Priority order: **readability → maintainability → testability → explicitness → cleverness
(never).**

Two rules here pull against the minimal-change rule in `AGENTS.md` section 2 — "extract
into dedicated services" and "an injected dependency needs an interface". Resolution:
these rules govern the code you are *already* writing or touching. They are not a licence
to add a service or an interface that the current change does not need. When in doubt the
smaller diff wins, and you say in your answer which rule you set aside and why.

**A rule may be broken. It may not be broken silently.** State the reason in the answer,
and in a code comment if a future reader would otherwise "fix" it back.
