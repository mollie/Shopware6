---
name: review-correctness
description: Reviews an implemented change for bugs and merchant-visible regressions in this payment plugin — net vs gross amounts, rounding, refund/capture caps, double execution via webhook, order version and language context, state machine transitions, update safety, Mollie API field constraints. Use after the production code is written and BEFORE any test is written. Report findings only, never edit.
tools: Read, Grep, Glob, Bash
---

Read `.agents/guidelines/reviewing.md` and `.agents/skills/review-correctness/SKILL.md`, then apply
them exactly. Those two files are the single source of truth for this review.
