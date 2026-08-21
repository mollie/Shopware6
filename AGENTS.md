# AI Rules — Mollie Payments for Shopware 6

Read this file first; everything else in the repository is looked up on demand.
Applies to maintainers and contributors alike; **Maintainers:** marks internal process.

## 1. State of the codebase

No refactoring is in progress. 5.x is released; what exists is the current state.

- `shopware/` — the source code. Namespace `Mollie\Shopware`. All work happens here.
- `src/` — only the plugin bootstrap (`MolliePayments.php`), DAL migrations and
  `Resources/` (admin + storefront assets, snippets, config.xml, twig). Not legacy to be
  migrated, not off-limits — just small.
- `tests/` — `Unit/`, `Integration/`, `Behat/`, `Cypress/`.
- `config/` — tooling config (PHPUnit, PHPStan, CS Fixer). No runtime logic.

Code inside `shopware/` is grouped per domain under `shopware/Component/<Domain>/`
(`Refund`, `Payment`, `Subscription`, `Shipment`, `Mollie`, …). Inside a component the
recurring sub-folders are `Controller/`, `Struct/`, `Event/`, `DAL/`, `Route/`, `Fake/`.
Find the pattern by reading the neighbouring component, not a document.

## 2. The smallest change that solves the problem

Optimise for a pull request a human can review in a few minutes.

- Prefer an added parameter over a new method. A new method over a new class. A new
  class over a new component.
- Do not create a new file unless there is no honest place for the code in an existing
  one. When you do create one, say in your answer why the existing files did not fit.
- Touch as few files as possible. If a change spreads across many files, stop and say so.
- No abstraction for a single caller. No interface, factory, strategy, event or config
  option that has exactly one use today.
- Search for what already exists before adding anything: services, structs, helpers.
  Reuse beats symmetry.
- Do not touch unrelated code: no renaming, reformatting or cleanups in the same change.
- Do not add backwards-compatibility layers, deprecation shims or feature flags unless
  asked.

If a simple fix and a thorough fix both exist, describe both in one or two sentences and
implement the simple one unless told otherwise.

## 3. Order of work

Do not skip ahead. Each step ends with something reviewable.

1. **Understand** — read the code that is actually involved. Report which files you read.
2. **Implement** — production code only, following section 2. **No tests yet.**
3. **Review** — run the reviewers from `.agents/reviews/` (see section 4) and report the
   findings.
4. **Stop** — get the approach confirmed by a human before going further. Fix the
   approach if it is rejected, then review again.
5. **Tests** — only now. See `.agents/guidelines/testing.md`.
6. **Changelog** — see section 6, then review it with `.agents/reviews/changelog.md`.
7. **Hand over** — a human runs the checks (section 5) and reports the result back.

## 4. Review before tests

After implementing, before writing any test, review the change. The checklists live in
`.agents/reviews/`, with the shared protocol in `.agents/reviews/README.md`:

| Reviewer | File | Looks for |
|---|---|---|
| Minimal diff | `.agents/reviews/minimal-diff.md` | over-engineering, unnecessary files, duplication |
| Conventions | `.agents/reviews/conventions.md` | senior PHP standard (`.agents/guidelines/php.md`) + Shopware rules |
| Correctness | `.agents/reviews/correctness.md` | bugs, edge cases, merchant-visible regressions |
| Tests | `.agents/reviews/tests.md` | run **after** step 5, on the tests themselves |
| Changelog | `.agents/reviews/changelog.md` | run **after** step 6, on the entries themselves |

The checklists are the single source of truth. Thin adapters point back at them, nothing more:

- `.claude/agents/*.md` — Claude Code, startable in parallel via the Agent tool
- `.codex/agents/*.toml` — Codex (TOML, `developer_instructions`), delegated by name

Any other tool: read the checklist files and apply them yourself.

`.agents/skills/` holds step-by-step recipes for recurring tasks; read the matching one first.
Adding or removing a Mollie payment method — `.agents/skills/payment-methods.md`.

## 5. Do not run the suites — hand over instead

**Do not run:** `make pr`, `make phpunit`, `make phpintegration`, `make behat`,
`make vitest`, `make stan`, `make csfix`, `make eslint`, `make stylelint`,
`make prettier`, `make phpunuhi`, `make configcheck`, `make build`, `make release`,
`composer install`, `npm install`, `curl`, `wget`.

When the change is ready, name the relevant targets and stop:

> Ready. Please run `make phpunit stan`.

Whoever is driving reports what failed; fix that. `make pr` must pass before a pull request.

**Allowed** when a shop is available: `bin/console`, cache clear, logs, real API probes. Ask
for the container name rather than guessing it.

Never install dependencies. Never print `.env` contents or API keys — if you need a value,
ask for that one variable by name.

## 6. Changelog

Every user-visible change gets an entry under `# Unreleased` in **both**
`CHANGELOG_de-DE.md` and `CHANGELOG_en-GB.md`. Merchants read this file, not developers.

- One short sentence per entry, describing the effect for the merchant. No root cause, no
  file names, no API details.
- Merge related changes into one entry. Three bullets about the same shipping bug is one
  bullet.
- Labels: `Hinzugefügt` / `Added`, `Geändert` / `Changed`, `Behoben` / `Fixed`, in that
  order within a section.
- Avoid a wall of `Behoben` / `Fixed`. Where new or changed behaviour honestly fits, label it
  that way — but "wieder" / "correctly again" wording stays `Behoben` / `Fixed`.
- Purely internal changes (refactoring, tests, tooling, CI) get **no** entry.

## 7. Code rules

The full standard is **`.agents/guidelines/php.md`** — read it before writing or reviewing PHP
in `shopware/`. The short version:

- `declare(strict_types=1);` in every file. Strong types everywhere, no `mixed`.
- Early returns. **No `else`, no `elseif`**, no nested conditionals.
- Classes are `final`. A constructor-injected class gets an interface so it can be faked.
- Avoid `null` — use a sensible default. Avoid `void` — return an assertable result.
- **Never `empty()`.** `count()` for collections, `strlen()` for strings.
- No raw arrays as data: struct, Value Object or Collection. Array chains go on a Collection.
- No magic values. Extract a constant.
- Small constructors. `LoggerInterface` last, with
  `#[Autowire(service: 'monolog.logger.mollie')]`. Optional arguments last.
- Domain exceptions from `shopware/Component/<Domain>/Exception/`, never bare `\Exception`.
- No query and no API call inside a loop.
- Services, tags and subscribers wired with **PHP attributes**, not `services.xml`.
- Descriptive names, no abbreviations. Comments only where the code cannot carry the intent.
- Snippets: every storefront/admin string goes through the snippet files, never hardcoded.

A rule may be broken, but never silently: say which one and why.

## 8. Answering

**Hard cap: five lines.** One line is normal. Exceed it only to list real findings or to ask a
question the human must answer — never to explain, summarise or reassure.

- **Lead with the result.** First line says what now holds. No preamble, no restating the task.
- **Do not narrate the work.** Never replay commands, output or file content you just wrote.
- **Do not justify unasked.** Only if non-obvious or against what was asked. One sentence.
- **No caveats.** Only a blocker the human must act on. Never a closing reservation.
- **No structure under ten lines.** No headers, no tables, no bold labels.
- Name the files you changed. Say what you deliberately left out.
- Do not write documentation or "learning" notes.

Do not commit, push or open a pull request unless asked.

**Maintainers:** branching, tickets, commit format — `.agents/workflows/workflow-ticket.md`.
**External contributors:** fork, branch freely, follow sections 2 to 7.

## 9. When you are corrected

A correction is a defect in these files, not just in the change.

After fixing it, ask: **which file would have caught this?** Edit it in the same breath: sharpen
the vague line and delete it, do not append a second rule beside it.

| The correction was about | It belongs in |
|---|---|
| how you work — scope, order, what to run, how to answer | this file |
| a PHP or test rule | `.agents/guidelines/php.md`, `.agents/guidelines/testing.md` |
| something a reviewer should have flagged | `.agents/reviews/<the relevant one>.md` |
| the steps of a recurring task | `.agents/skills/` |

Prefer the reviewer: a guideline gets read if someone looks, a checklist line gets checked.

- **No new file for a single correction.** No running log of learnings, no `corrections.md`.
  The four homes above are enough; if a correction fits none of them, say so and ask.
- **A fact is not a rule.** An undocumented Mollie or Shopware quirk is not process feedback —
  mention it in your answer and ask whether to record it; do not file it somewhere.
- **State what you changed.** One line: which file, which rule now reads differently.
- **The test:** would this correction now be caught without the human saying anything? If
  not, it went in the wrong place.
