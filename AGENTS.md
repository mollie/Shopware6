# AI Rules — Mollie Payments for Shopware 6

Read this file first; everything else in the repository is looked up on demand.
Applies to maintainers and contributors alike; **Maintainers:** marks internal process.

**This file wins.** A working copy may carry an `.ai/` directory with further rules that are
not specific to this repository. It is optional — if it is not there, nothing is missing.
Where it says one thing and this file says another, follow this file: it knows the plugin.
Do not edit `.ai/`; it is not maintained here (see section 9).

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

0. **Ask** — before you read anything. See the rules below.
1. **Understand** — read the code that is actually involved. Report which files you read.
   For subscriptions or vouchers, read `.agents/guidelines/domain.md` first: it holds the
   Mollie behaviour the code depends on but cannot state itself.
2. **Implement** — production code only, following section 2. **No tests yet.**
3. **Review** — run the reviewers from `.agents/skills/review-*/` (see section 4) and report
   the findings.
4. **Stop** — get the approach confirmed by a human before going further. Fix the
   approach if it is rejected, then review again.
5. **Tests** — only now. See `.agents/guidelines/testing.md`.
6. **Changelog** — see section 6, then review it with `.agents/skills/review-changelog/`.
7. **Hand over** — a human runs the checks (section 5) and reports the result back.

### Step 0 — ask before you search

A wrong assumption costs more than a question. The developer knows this codebase; you do
not. Searching for something they could have named in one line is the most common way this
goes wrong, and it produces code that is too complex because it was built around a guess.

- **At most five questions, in a single message**, before you open a file. Then wait.
- **Ask where something is** instead of grepping for it: *"which component handles the
  refund cap?"* beats four searches and a wrong guess.
- **Ask whether it already exists.** *"Is there already a service that does X?"* — the answer
  is often yes, and then the change is one line instead of a new class.
- **Ask what the expected behaviour is in the edge case** the ticket does not mention.
- **Never ask for a value from `.env`** or a secret — see section 5.

Then a budget: read the files you were pointed at and their direct callers. If that is not
enough to be sure, **ask again rather than widen the search**. Two rounds of questions is
cheaper than one wrong implementation.

The counter-rule, so this does not turn into an interrogation: **no question whose answer is
in a file you have to read anyway**, and no question you can answer by reading the
neighbouring component for the pattern. If the developer answers *"find it yourself"*, do
that and do not ask again for that thing.

## 4. Review before tests

After implementing, before writing any test, review the change. Each reviewer is an Agent
Skill under `.agents/skills/`, sharing the protocol in `.agents/guidelines/reviewing.md`:

| Reviewer | Skill | Looks for |
|---|---|---|
| Minimal diff | `review-minimal-diff` | over-engineering, unnecessary files, duplication |
| Conventions | `review-conventions` | senior PHP standard (`.agents/guidelines/php.md`) + Shopware rules |
| Correctness | `review-correctness` | bugs, edge cases, merchant-visible regressions |
| Tests | `review-tests` | run **after** step 5, on the tests themselves |
| Changelog | `review-changelog` | run **after** step 6, on the entries themselves |

Run the first three together, then report. Do not write a test before they have been run.

## 4a. How the tools find all this

`.agents/skills/<name>/SKILL.md` is the [Agent Skills](https://agentskills.io) format and the
single source of truth for every skill in this repository — reviewers included.

- **Codex, OpenCode, Cursor, Copilot, Gemini CLI** read `.agents/skills/` natively. Nothing
  to configure; the skill loads when the task matches its description.
- **Claude Code** reads `.claude/`, so it gets thin pointers: `.claude/agents/review-*.md`
  (subagents, so the five reviews run in parallel in their own context) and
  `.claude/skills/*/SKILL.md` for the rest. They contain a path and nothing else.
- **Any other tool:** read the `SKILL.md` yourself and apply it.

A pointer never carries content. When a rule changes, it changes in `.agents/` only.

Recurring tasks live here too — adding or removing a Mollie payment method is
`.agents/skills/payment-methods/`, and setting the release version before internal QA is
`.agents/skills/increase-version/`.

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
- **No entry for anything nobody was waiting for:** refactoring, tests, tooling, CI — and a
  fix that only surfaced while writing tests and was never reported by anyone. The changelog
  answers what changed for the merchant since the last version, not what we touched. This
  overrides the first rule above: "user-visible" alone is not enough.

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
the vague line and delete it, do not append a second rule beside it. The step-by-step is the
`record-correction` skill — run it every time you are corrected.

| The correction was about | It belongs in |
|---|---|
| how you work — scope, order, what to run, how to answer | this file |
| a PHP or test rule | `.agents/guidelines/php.md`, `.agents/guidelines/testing.md` |
| how Mollie or Shopware actually behaves | `.agents/guidelines/domain.md` |
| something a reviewer should have flagged | `.agents/skills/review-<the relevant one>/SKILL.md` |
| the steps of a recurring task | `.agents/skills/<the task>/SKILL.md` |
| a rule that holds beyond this repository | `.ai/`, if present — **name it, do not edit it** |

Prefer the reviewer: a guideline gets read if someone looks, a checklist line gets checked.

`.ai/` is not maintained here, so an edit to it does not survive. Name the file and the line
that should change and leave it to the developer.

- **No new file for a single correction.** No running log of learnings, no `corrections.md`.
  The five homes above are enough; if a correction fits none of them, say so and ask.
- **A fact is not a rule.** An undocumented Mollie or Shopware behaviour is not process
  feedback. It belongs in `.agents/guidelines/domain.md`, not in the rule files above — ask
  before writing it there, and only write what a maintainer confirmed.
- **State what you changed.** One line: which file, which rule now reads differently.
- **The test:** would this correction now be caught without the human saying anything? If
  not, it went in the wrong place.
