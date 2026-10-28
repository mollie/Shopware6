---
name: record-correction
description: Turn a correction from the developer into a permanent rule change so the same mistake does not come back, and write down Mollie or Shopware behaviour a maintainer explained. Use whenever the developer rejects, corrects or overrides something, and whenever a maintainer explains how Mollie or Shopware really behaves. Trigger words — "no, do it this way", "that is wrong", "not like that", "I already told you", "nein, so nicht", "das ist falsch", "hab ich schon gesagt", "merk dir das", "schreib das auf", "das macht Mollie so".
---

# Record a correction

Run after the correction itself is applied, in the same turn.

## 1. Rule or fact?

- **Rule** — how code, tests or work should be done. Name it as a rule, not as the symptom:
  not "I used `empty()`", but "php.md bans `empty()` without naming `count()`/`strlen()`".
- **Fact** — how Mollie or Shopware actually behaves. Only when a maintainer confirmed it;
  never a guess.

## 2. Pick the one file

| About | File |
|---|---|
| how to work: scope, order, what to run, how to answer | `AGENTS.md` |
| PHP or Shopware code rule, review check | `.agents/guidelines/php.md` or `.agents/skills/review/SKILL.md` |
| test rule | `.agents/guidelines/testing.md` |
| changelog wording | `.agents/skills/changelog/SKILL.md` |
| steps of a recurring task | `.agents/skills/<task>/SKILL.md` |
| Mollie or Shopware behaviour | `.agents/guidelines/domain.md` |
| support know-how for merchants | `docs/` |

## 3. Edit it

- Sharpen the existing line instead of adding a second one beside it.
- No new file, no log of learnings, no dated entries.
- In `domain.md`: one `##` section per topic, a handful of sentences, fact first, then what
  follows for the code. Extend an existing section rather than adding one. Nothing that can
  be read from the code.

## 4. Report

One line: which file, and how the rule now reads.

Test: would this now be caught without the developer saying anything? If not, wrong file.
