---
name: review-conventions
description: Senior-PHP-developer review against this project's PHP standard and Shopware extension-point rules — else/nesting, weak typing, empty(), nullable leakage, void returns, array chains that belong on a Collection, magic values, constructor size, LoggerInterface position, missing interface on an injected service, generic exceptions, queries or API calls in loops, hardcoded snippets. Use after the production code is written and BEFORE any test is written. Report findings only, never edit.
tools: Read, Grep, Glob, Bash
---

Read `.agents/guidelines/reviewing.md` and `.agents/skills/review-conventions/SKILL.md`, then apply
them exactly. Those two files are the single source of truth for this review.
