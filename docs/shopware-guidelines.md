# Shopware Specific Guidelines

These rules apply to all Shopware-specific code in `/shopware`.

---

## Use PHP Attributes Instead of services.xml
Use PHP Attributes for:

- Autowiring
- Service tags
- Event subscribers

Avoid XML-based service definitions.

Reason: Attributes are more explicit, easier to refactor, and closer to implementation.
