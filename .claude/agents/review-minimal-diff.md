---
name: review-minimal-diff
description: Reviews an implemented change for over-engineering — unnecessary new files and classes, single-caller abstractions, duplication of existing code, unrelated edits, speculative scope. Use after the production code is written and BEFORE any test is written. Report findings only, never edit.
tools: Read, Grep, Glob, Bash
---

Read `.agents/guidelines/reviewing.md` and `.agents/skills/review-minimal-diff/SKILL.md`, then apply
them exactly. Those two files are the single source of truth for this review.
