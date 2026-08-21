---
name: review-changelog
description: Reviews the CHANGELOG_de-DE.md and CHANGELOG_en-GB.md entries before hand-over — too many bullets for one behaviour, a pile-up of Fixed entries that makes the plugin look broken, wrong label for the wording, developer detail that should not be merchant-facing, entries for internal-only changes, missing entries, de/en drift. Use after the changelog is written. Report findings only, never edit.
---

# Review: Changelog

Follow `.agents/guidelines/reviewing.md` for the shared protocol, then this checklist.
Diff both changelogs against the code diff of the same change, so you can see missing entries
and entries that should not exist. Judge every line as a shop owner who does not know this
codebase. Where you propose a cut or merge, give the exact replacement sentence in both
languages — "too long" alone is not actionable.

Run this **after** the changelog entries are written, before hand-over.

## Check

- **Too many entries.** Count the new bullets against what actually changed. Several bullets
  describing one behaviour are one bullet. Name which ones to merge and give the merged
  sentence.
- **`Behoben` / `Fixed` pile-up.** A section that is mostly fixes makes the plugin look
  broken. Per fix, ask whether it honestly reads as new or changed behaviour — but never
  relabel a real bug. The label must match the wording: *wieder* / *correctly again* is a
  fix, and relabelling means rewriting the sentence so it no longer reads like a repair.
- **Order within a section.** `Hinzugefügt`/`Added`, then `Geändert`/`Changed`, then
  `Behoben`/`Fixed` last.
- **Developer detail that leaked in.** Root causes, class or file names, API field names,
  Shopware version internals, ticket numbers. Cut them. The entry says what changes for the
  merchant, nothing else.
- **Too long.** One sentence per entry. If it needs a comma-spliced explanation, the
  explanation is the part to drop.
- **Entry that should not exist.** Refactoring, tests, tooling, CI and internal renames get
  no entry at all.
- **Missing entry.** A merchant-visible change in the code diff with no line in the
  changelog.
- **de/en drift.** Both files must carry the same entries in the same order, and both must
  read as native — not one translated word-for-word from the other.
- **Wrong section.** Entries belong under `# Unreleased`, not appended to a released
  version.

## Output

One line per finding: `file:line — problem — the replacement sentence`. For a merge, the
single sentence that replaces the group.
