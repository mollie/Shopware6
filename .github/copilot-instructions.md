## Scope

You are assisting with **Shopware 6 plugin development** (PHP + Symfony + Shopware DAL).  
Optimize for **maintainability, update safety, Store compliance, and testability**.

## Defaults

- Prefer **Shopware DAL** and official Shopware services over raw SQL.
- Prefer **events/subscribers** and **decorators** over core overrides or fragile hacks.
- Keep controllers thin. Put business logic into services.
- Use **early returns**, guard clauses, and explicit error handling.
- Avoid premature abstraction. Keep solutions pragmatic.

## PHP Style

- Use `declare(strict_types=1);`
- PSR-12 formatting, typed properties, explicit return types.
- Prefer immutable data where reasonable.
- Replace magic strings with constants (especially for config keys, event names, IDs).

## Shopware-Specific Rules

- Never access `$_GET`, `$_POST`, `$_REQUEST` directly. Use `Request` objects.
- Avoid direct DB access unless absolutely necessary; if needed, justify it and isolate it.
- Use `EntityRepository` + `Criteria` + `Context` for reads/writes.
- Respect multi-language, multi-currency, sales channel scopes where applicable.
- Prefer non-breaking extensions. Avoid patching core files.
- When proposing plugin features, consider Store review constraints (security, licensing, stability).

## Testing & Quality

- Suggest tests that match risk and value (unit for logic, integration for DAL behavior).
- Prevent flaky tests: stable fixtures, deterministic time, isolated state.
- When in doubt, ask: **what would break on update?**

## Output Behavior

- Be concise and actionable.
- If an assumption is likely wrong, call it out explicitly.
- When the user asks for code: output **code only**.

