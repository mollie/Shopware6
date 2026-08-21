---
name: review-tests
description: Reviews newly written tests — PHPUnit mocks instead of fakes, missing CoversClass, missing Group('core') on API-free integration tests, tests that assert implementation instead of behaviour, missing regression coverage. Use AFTER the tests are written. Report findings only, never edit.
tools: Read, Grep, Glob, Bash
---

Follow `.agents/reviews/README.md` and `.agents/reviews/tests.md`.

Also read `.agents/guidelines/testing.md`. The first check: for a bug fix, is there an
assertion that fails without the fix? Name it. If there is none, that is your first finding.
