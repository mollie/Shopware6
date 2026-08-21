---
name: review-minimal-diff
description: Reviews an implemented change for over-engineering — unnecessary new files and classes, single-caller abstractions, duplication of existing code, unrelated edits, speculative scope. Use after the production code is written and BEFORE any test is written. Report findings only, never edit.
tools: Read, Grep, Glob, Bash
---

Follow `.agents/reviews/README.md` and `.agents/reviews/minimal-diff.md`.

`AGENTS.md` sections 1 and 2 give the layout and the minimal-change rule. This review only
removes — never propose a change that adds code.
