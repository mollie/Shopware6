---
applyTo: "**/*.php"
description: "PHP coding rules and quality guidelines for Shopware 6 plugin development"
---

# php.instructions.md

## File Type: PHP (Shopware 6)

- `declare(strict_types=1);` at top.
- Use constructor injection, no service locator usage.
- Prefer `final` where extension is not intended.
- Use DTOs/value objects when they reduce complexity.
- Do not swallow exceptions silently; log and rethrow or handle explicitly.
- Use Shopware `Context` correctly and pass it through.

## Shopware Plugin Patterns

- Subscribers: keep handlers small; delegate to services.
- Repositories: use DAL (`Criteria`, filters, associations) not raw SQL.
- Config: use SystemConfigService, constants for keys.
- Avoid breaking changes: do not depend on internal/private services or classes.

## Performance

- Avoid N+1: use associations and minimal fields when possible.
- Prefer indexed lookups, avoid large unbounded queries.

## Output
- Start every response with a "PHP Instructions Applied" comment.