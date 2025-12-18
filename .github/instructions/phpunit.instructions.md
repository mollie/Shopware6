---
applyTo: "**/*Test.php"
description: "PHPUnit testing rules – simple, readable, fake-first, Shopware-friendly"
---

## Core Testing Philosophy

- Tests must be **simple, readable, and explicit**.
- Prefer **clarity over abstraction**.
- A test should explain **what** is verified and **why**, even without reading the production code.
- If a test feels complicated, simplify the production code or the test setup.

## Structure & Style

- Use a clear **Arrange / Act / Assert** structure.
- One behavior per test.
- Avoid deeply nested logic in tests.
- Prefer multiple small tests over one complex test.

## Comments

- Use comments intentionally:
    - Explain **intent**, not implementation.
    - Especially useful for array structures, edge cases, or domain logic.
- Comments should help future readers understand **why this test exists**.
- Avoid redundant comments that restate the code.

## Naming

- Name tests by behavior:
    - `testResponseNotSuccessWithoutCustomer`
    - `testItemQuantityIsZero`
- Avoid technical or internal naming.
- The test name should read like a sentence.

## Test Data

- Keep test data **explicit and inline** unless it becomes repetitive.
- Prefer readable literals over builders with hidden defaults.
- Make numbers and strings obvious; avoid magic behavior.

## Data Providers

- Use **data providers when it improves readability**:
    - Multiple input/output combinations
    - Edge cases with the same behavior
- Do **not** use data providers if they make the test harder to understand.
- Each dataset should clearly describe the scenario it represents.

## Fakes vs Mocks

- Prefer **fake implementations** over mocks.
- Avoid mocking via interfaces unless interaction verification is the goal.
- Fakes should:
    - Be simple
    - Be deterministic
    - Live close to the test (same file or test namespace)
- Do not overuse PHPUnit mocks for domain logic.

## Shopware Context

- Use real entities (e.g. `OrderLineItemEntity`) where feasible.
- Avoid kernel boot unless strictly required.
- Pass `Context` explicitly when relevant.
- Do not rely on existing database or system state.

## Stability

- Tests must be deterministic and order-independent.
- Avoid time-based behavior unless time is controlled.
- Avoid randomness unless explicitly seeded.

## Quality Bias

- Tests document behavior.
- If a test is hard to read, it is a code smell.
- Prefer boring tests that fail clearly.
- Assume someone else will debug a failing test at 3 AM.

## Output

- Start every response with a "PHPUnit Instructions Applied" comment.