---
name: record-correction
description: Turn a correction from the developer into a permanent rule change, so the same mistake cannot come back. Use whenever the developer rejects, corrects or overrides something you did or proposed — "no, do it this way", "that is wrong", "not like that", "I already told you" — and after a review finding that the checklists should have caught. Decides which rule file was defective, edits that one file, and reports the change.
---

# Record a correction

A correction is a defect in the rule files, not just in the change. `AGENTS.md` section 9
holds the principle and the routing table; this skill is how you execute it.

Run this **after** the correction itself is applied to the code, in the same turn.

## 1. Name what was actually wrong

One sentence, in terms of the rule — not the symptom.

> Not: *"I used `empty()`."* — Instead: *"the conventions checklist lists `empty()` but not
> the `!count()` replacement, so the fix was not obvious."*

If you cannot phrase it as a rule, it is a **fact**, not process feedback: an undocumented
Mollie or Shopware quirk, a value only the developer knows. Say so in your answer, ask
whether to record it, and **stop here**. Do not file it.

## 2. Pick the one file that should have caught it

Use the routing table in `AGENTS.md` section 9. Two tie-breakers:

- **Prefer the reviewer.** A guideline gets read if someone looks; a checklist line gets
  checked. If the correction fits both a guideline and a `review-*` skill, edit the skill.
- **Does the rule hold beyond this repository?** Then it belongs in `.ai/`, if that directory
  is present at all. Do not edit it — it is not maintained here and the edit does not
  survive. Name the file and the line that should change and leave it to the developer.

## 3. Edit that one file

- **Sharpen the vague line and delete it.** Do not append a second rule beside it. Two rules
  covering the same ground is how these files rot.
- **No new file.** The homes in the section 9 table are enough. If the correction fits none
  of them, say so and ask.
- **No running log.** No `learnings.md`, no `corrections.md`, no dated entries. An unbounded
  list of past mistakes adds noise and gets skimmed.
- Keep the line in the voice of the file it lands in: a checklist gets a checkable line, a
  guideline gets a rule.

## 4. Report it, one line

Which file, and how the rule now reads differently. Not what you learned.

> `.agents/skills/review-conventions/SKILL.md` — the `empty()` line now names `count()` /
> `strlen()` as the replacement instead of only banning it.

## The test

Would this correction now be caught **without the developer saying anything**? If the answer
is no, it went in the wrong file — go back to step 2.
