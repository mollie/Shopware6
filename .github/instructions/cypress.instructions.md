---
applyTo: "**/*.cy.js"
description: "Cypress E2E rules – keyword-driven actions, simple tests, TestRail ID in title"
---

## Core Style

- Tests must be **keyword-driven** using Actions/Scenarios (high-level steps).
- Keep tests **super simple** and readable.
- Prefer explicit steps over clever abstractions.
- One user flow per `it`.

## TestRail Identifier

- Every test title must start with the TestRail case ID:
    - `it('C4028: <short human title>', () => { ... })`
- Format is mandatory and consistent.
- Cypress tests are automating prepared TestRail test cases and should not invent new flows.

## Structure

- Use a clear step flow:
    - Setup (environment + session + device)
    - Arrange (scenario data / payment method / config)
    - Act (place order / execute action)
    - Assert (admin/storefront assertions)
- Use short, meaningful comments only when they add intent.

## Keyword-Driven Actions

- Prefer `Actions/*` and `Scenarios/*` for all steps.

## Determinism & Flake Prevention

- Never use arbitrary waits (`cy.wait(1000)`).
- Wait for observable state via:
    - Action-level waiting
    - network aliases (if used)
    - UI state changes
- Reset browser session and ensure device setup is explicit per test.

## Data & State

- Do not rely on existing shop data.
- Always create deterministic test state through scenarios or API-based setup.
- Clean up or isolate side effects where necessary.

## Assertions

- Assertions must be **high value** and minimal:
    - Order status
    - Payment status
    - Key UI outcomes
- Avoid over-asserting technical details.

## Version-Specific Logic

- Keep version checks minimal and well-commented.
- Prefer encapsulating version differences inside Actions or helpers (e.g. `Shopware` utility),
  not scattered throughout tests.

## Performance Bias

- Keep E2E tests short.
- Push edge cases down to unit/integration tests when possible.

## Output Expectations

- If asked to refactor, keep the behavior identical.
- If asked to create new tests, follow the same folder conventions and naming scheme.