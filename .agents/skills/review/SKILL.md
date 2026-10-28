---
name: review
description: Review a change in this plugin in one pass — size of the diff first, then every changed file against the PHP and Shopware rules, then whether the code duplicates something that already exists. Runs in the main context, no sub-agents. Use after implementing and before writing tests, or when asked to check a diff, branch or pull request. Trigger words — "review", "review the change", "check the diff", "code review", "prüf den Code", "review machen", "schau dir die Änderung an", "ist das so ok".
---

# Review

One pass, in this order. Stop at the first step that fails and report it. Report only,
never edit. Do not run test suites or static analysis.

## 1. Size

```bash
git diff --stat <base>...HEAD   # or plain `git diff --stat` for uncommitted work
```

Ignore snippet and language files (`src/Resources/snippet/`,
`src/Resources/app/administration/src/snippet/`, `CHANGELOG_*`) — many of those are fine. Count the remaining files and lines.

If a small behavioural change touches many files or adds new classes, interfaces or files,
the approach is probably too complicated. Say so, name which files could be dropped and
where the change could have lived instead, and stop.

## 2. Each file

Read every changed file in full against `.agents/guidelines/php.md`, including its Shopware
section. Compare with the neighbouring component: sibling code is the convention.
Not reported: anything PHPStan, CS Fixer or ESLint catch on their own.

On payment paths also check:

- Gross amount sent to Mollie, also for net-priced orders.
- Refund capped by the captured amount, capture by the authorisation.
- Webhook delivered twice: second run is a no-op.
- Right order version, language and transaction.
- State changes through the state machine.

## 3. Does it already exist

Understand what the new code does. Search `shopware/` for the domain nouns of the diff —
a service, struct, helper or calculation that already does it is a finding, even if the new
code is nicer. Same for unused code, unrelated edits and handling of cases no caller produces.

## Output

One line per finding, worst first: `path:line — problem — fix`. Nothing found: `nothing`.
