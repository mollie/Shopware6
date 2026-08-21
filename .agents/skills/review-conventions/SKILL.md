---
name: review-conventions
description: Senior-PHP-developer review against this project's PHP standard and Shopware extension-point rules — else/nesting, weak typing, empty(), nullable leakage, void returns, array chains that belong on a Collection, magic values, constructor size, LoggerInterface position, missing interface on an injected service, generic exceptions, queries or API calls in loops, hardcoded snippets. Use after the production code is written and BEFORE any test is written. Report findings only, never edit.
---

# Review: Conventions

Follow `.agents/guidelines/reviewing.md` for the shared protocol, then this checklist.
You are a senior PHP developer. Compare against the neighbouring component; sibling code is
the convention. For each violation give the concrete consequence and the concrete fix, code
only where the fix is not obvious. A rule broken deliberately for a good reason but without
that reason stated is also a finding.

The standard is `.agents/guidelines/php.md` — read it, it is the checklist. This file adds
the Shopware-specific rules on top.

## Shopware-specific

- Services, tags, subscribers and route defaults wired via **PHP attributes**, not
  `services.xml`.
- Subscribers: correct event class; attribute form used where available.
- Store-API routes: `#[Route]` with `defaults` including `_routeScope`; response is a
  `StoreApiResponse`/struct, not a raw array.
- DAL: entity/definition/collection triple complete and consistent, extensions registered,
  no raw SQL where the repository would do.
- Order state changes go through the state machine, never by writing the state field.
- Storefront and admin strings come from snippet files. A hardcoded German or English
  string in twig, JS or PHP is a finding.
- Cross-version Shopware APIs belong in `shopware/Component/Compatibility/`, not behind an
  inline `version_compare()`.
- Config keys via `SystemConfigService` with constants, never a literal key string.
- DAL queries bounded: a search without a limit over a merchant-sized table is a finding, as
  is a missing association that turns into a lazy load per row.
- New code sits under `shopware/Component/<Domain>/` in the sub-folder its siblings use
  (`Controller/`, `Struct/`, `Event/`, `DAL/`, `Route/`); namespace matches the path.

## How to report

Two lines per finding at most. Where several solutions exist, name the one that best
satisfies the guidelines. A rule broken deliberately needs its reason in the answer, and in
a code comment if a later reader would otherwise revert it.

## Do not report

- Design scope and over-engineering — `.agents/skills/review-minimal-diff/SKILL.md` covers that. In
  particular do **not** demand a new service or interface that this change does not need.
- Missing tests — tests come after this review by design.
