---
name: review-tests
description: Reviews newly written tests — PHPUnit mocks instead of fakes, missing CoversClass, missing Group('core') on API-free integration tests, tests that assert implementation instead of behaviour, missing regression coverage. Use AFTER the tests are written. Report findings only, never edit.
---

# Review: Tests

Follow `.agents/guidelines/reviewing.md` for the shared protocol, then this checklist.
Also read `.agents/guidelines/testing.md`. The first check: for a bug fix, is there an
assertion that fails without the fix? Name it. If there is none, that is your first finding.

Run this **after** the tests are written.

- **No PHPUnit mocks.** `createMock()`, `getMockBuilder()`, `prophesize()` are findings.
  Use a Fake from the neighbouring `Fake/` folder, or add one. When the assertion is about
  *what was sent*, use a Spy — a Fake that records its calls — not a mock.
- **`#[CoversClass(...)]`** declared on every unit test class.
- **Builders** for complex fixtures instead of long inline object setup — reuse
  `tests/Unit/Builder/` and the per-component `Builder/` folders.
- **`#[Group('core')]`** on every integration test that does *not* call the Mollie API;
  API-dependent tests stay untagged — why, in `.agents/guidelines/testing.md`.
- **Asserted behaviour, not implementation.** A test that only asserts a setter was called,
  or that mirrors the production code line by line, is a finding.
- **Shopware fixtures that blow up at runtime.** Three that a green-looking test hides:
  a `set*(null)` call on a Shopware entity — almost every association setter is *not*
  nullable, so "without a country" means never calling `setCountry()`, not passing `null`
  (only `OrderEntity::setLanguage()` and `setPrimaryOrderDelivery()` accept it); an entity
  without `setId()` that goes into an `EntityCollection` — it throws on
  `$_uniqueIdentifier`; and two `CalculatedTax` entries with the same `taxRate` in one
  `CalculatedTaxCollection` — that collection is keyed by rate, so the second silently
  replaces the first and the multi-rate branch is never reached.
- **The regression is covered.** For a bug fix: is there a test that fails without the fix?
  Name the assertion. If none, that is the most important finding.
- **Edge cases from the correctness review** — each accepted one should have a case.
- **No new abstraction in the test layer** for a single test.
- **Test placement** mirrors the production path: `shopware/Component/Refund/X.php` →
  `tests/Unit/Refund/XTest.php`.
