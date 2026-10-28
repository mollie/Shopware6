# AI Rules — Mollie Payments for Shopware 6

If the working copy has an `.ai/` directory, use the skills in there as well. Where they
contradict this file, this file wins. Do not edit `.ai/`.

## Layout

- `shopware/Component/<Domain>/` — all code, namespace `Mollie\Shopware`. Sub-folders
  `Controller/`, `Struct/`, `Event/`, `DAL/`, `Route/`, `Fake/`, `Exception/`. Copy the pattern
  from the neighbouring component.
- `src/` — bootstrap, migrations, `Resources/` (admin, storefront, snippets, config.xml).
- `tests/` — `Unit/`, `Integration/`, `Behat/`, `Cypress/`.
- `docs/` — documentation for humans.

## Workflow

1. **Ask first.** Up to five questions in one message: where does X live, does X already
   exist, what should happen in the edge case. No question whose answer is in a file you
   have to read anyway.
2. **Understand.** For payment status, webhooks, captures, amounts, subscriptions or
   vouchers read `.agents/guidelines/domain.md` first.
3. **Implement** production code only, then run the `review` skill once and wait for
   the developer's go.
4. **Tests** after that — `.agents/guidelines/testing.md`.
5. **Changelog** — `changelog` skill.
6. When corrected, or when a maintainer explains Mollie or Shopware behaviour: `record-correction` skill.

Maintainers: branch and commit format in `.agents/workflows/workflow-ticket.md`.

## Smallest change

- Parameter before method, method before class, class before component.
- New file only when no existing one fits; say why.
- Many touched files means the approach is too complicated. Stop and say so.
- No abstraction for a single caller, no BC shims, no feature flags, no unrelated edits.
- Search for an existing service, struct or helper before adding one.

## Code

Full standard: `.agents/guidelines/php.md`. In short: `strict_types`, `final`, early return,
no `else`, no `empty()`, no `mixed`, no `void`, avoid `null`, structs/collections instead of
arrays, constants instead of magic values, PHP attributes instead of `services.xml`,
`LoggerInterface` last with `#[Autowire(service: 'monolog.logger.mollie')]`, domain
exceptions, no query or API call in a loop, snippets instead of hardcoded strings, **no comments**.

A rule may be broken, never silently: say which one and why.

## Do not run

`make` targets (`pr`, `phpunit`, `phpintegration`, `behat`, `vitest`, `stan`, `csfix`, …),
`composer install`, `npm install`, `curl`, `wget`. Name the targets instead:
"Ready. Please run `make phpunit stan`."

Allowed: `bin/console`, cache clear, logs. Never print `.env` or API keys; ask for one
variable by name.

## Answering

Five lines at most, result first, name the changed files and what you left out. No
narration, no caveats. No commit, push or pull request unless asked.
