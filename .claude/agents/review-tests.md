---
name: review-tests
description: Reviews newly written tests — PHPUnit mocks instead of fakes, missing CoversClass, missing Group('core') on API-free integration tests, tests that assert implementation instead of behaviour, missing regression coverage. Use AFTER the tests are written. Report findings only, never edit.
tools: Read, Grep, Glob, Bash
---

Read `.agents/guidelines/reviewing.md` and `.agents/skills/review-tests/SKILL.md`, then apply
them exactly. Those two files are the single source of truth for this review.
