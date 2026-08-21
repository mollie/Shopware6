---
name: review-changelog
description: Reviews the CHANGELOG_de-DE.md and CHANGELOG_en-GB.md entries before hand-over — too many bullets for one behaviour, a pile-up of Fixed entries that makes the plugin look broken, wrong label for the wording, developer detail that should not be merchant-facing, entries for internal-only changes, missing entries, de/en drift. Use after the changelog is written. Report findings only, never edit.
tools: Read, Grep, Glob, Bash
---

Read `.agents/guidelines/reviewing.md` and `.agents/skills/review-changelog/SKILL.md`, then apply
them exactly. Those two files are the single source of truth for this review.
