# Review: Conventions

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

- Design scope and over-engineering — `.agents/reviews/minimal-diff.md` covers that. In
  particular do **not** demand a new service or interface that this change does not need.
- Missing tests — tests come after this review by design.
