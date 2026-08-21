---
name: review-changelog
description: Reviews the CHANGELOG_de-DE.md and CHANGELOG_en-GB.md entries before hand-over — too many bullets for one behaviour, a pile-up of Fixed entries that makes the plugin look broken, wrong label for the wording, developer detail that should not be merchant-facing, entries for internal-only changes, missing entries, de/en drift. Use after the changelog is written. Report findings only, never edit.
tools: Read, Grep, Glob, Bash
---

Follow `.agents/reviews/README.md` and `.agents/reviews/changelog.md`.

Diff both changelogs and the code diff of the same change, so you can see missing entries
and entries that should not exist. Judge every line as a shop owner who does not know this
codebase. Where you propose a cut or merge, give the exact replacement sentence in both
languages — "too long" alone is not actionable.
