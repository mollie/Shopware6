# Mollie Payment Plugin for Shopware 6

## Overview

The Mollie Payment Plugin is a payment integration for Shopware 6.
It is developed and maintained by Mollie.

This documentation is intended for:

- Developers working on the plugin
- Contributors
- Automated systems (e.g. AI tools) that use this repository as contextual knowledge

The goal of this documentation is to describe:

- Project structure
- Architecture
- Workflows
- Technical decisions
- Tooling and development standards

---

## Project Structure

The following folders exist in the root directory of the project:

### `/config`

Contains configuration files for development tools.

Examples:
- PHPUnit configuration
- PHPCSFixer rules
- Static analysis tools
- CI configuration

This folder does **not** contain runtime application logic.

---

### `/docs`

Contains the project documentation.

All architectural decisions, workflows, and developer guidelines should be documented here.

This folder is the main knowledge source for developers and AI systems.

---

### `/polyfill`

Contains compatibility layers for different Shopware versions.

Purpose:
- Provide backward compatibility
- Abstract version-specific Shopware behavior
- Avoid conditional logic scattered across the codebase

All version-specific adjustments should be isolated here whenever possible.

---

### `/shopware`

Contains the **current primary source code** of the plugin.

Rules:
- All new PHP classes must be created inside this folder.
- The root namespace for all new code is:

  `Mollie\Shopware`

This directory represents the future-proof architecture of the plugin.

---

### `/src`

Contains legacy source code.

Important:
- This folder is being phased out.
- All features should gradually be migrated to `/shopware`.
- No new features should be implemented here unless absolutely necessary.

Long-term goal: complete removal of this directory.

---

### `/tests`

Contains all automated tests.

Includes:
- Unit tests
- Integration tests
- Functional tests (if applicable)

Tests should always reflect the structure of `/shopware`.

---

### `/vendor_manual`

Contains the Mollie PHP SDK dependency.

Background:
Older Shopware versions require dependencies to be shipped directly within the plugin source code.

This folder exists to ensure compatibility with those versions.

---

## Architectural Principles

- Clear separation between legacy and new code
- Strict namespace organization
- Backward compatibility via polyfills
- Gradual migration strategy from `/src` to `/shopware`
- Test-driven and tool-supported development

---

## Intended Audience

This documentation is written for:

1. Backend Developers
2. Plugin Maintainers
3. Code Reviewers
4. AI systems that analyze or generate code based on this repository

Therefore:
- Structure must remain explicit
- Folder purposes must always be clearly documented
- Architectural decisions should be recorded

---
## Documentation Overview

For more detailed guidance and rules, see:

- **Architecture Overview** – `/docs/architecture.md`
  Key entry points and services in the plugin
- **Payment Methods** – `/docs/payment-methods.md`
  How payment methods are structured, registered, and implemented; checklist for adding a new method
- **Coding Guidelines** – `/docs/coding-guidelines.md`
  Rules for writing new code, class design, DTOs, etc.
- **Testing Guidelines** – `/docs/testing-guidelines.md`
  Rules for fakes, builders, and unit test coverage
- **Shopware Guidelines** – `/docs/shopware-guidelines.md`
  Attributes, extension points, state machine rules

This structure allows developers and AI systems to quickly locate the relevant information without reading unnecessary details.


---

## Contribution Guideline (Short Version)

- New code → `/shopware`
- Version compatibility → `/polyfill`
- No new logic in `/src`
- Tests required for new features
- Follow project coding standards
- If a code part was changed, add a summary in custom/plugins/MolliePayments/CHANGELOG*.md under `unreleased` Section
- Run `make pr` to check if the code is working
