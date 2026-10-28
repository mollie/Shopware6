---
name: changelog
description: Write or check the merchant-facing entries in CHANGELOG_de-DE.md and CHANGELOG_en-GB.md under "# Unreleased" — one short sentence per behaviour, both languages in sync, no developer detail, no entries for internal work, no wall of Fixed. Use when a change is ready for hand-over or when asked to write, update or review the changelog. Trigger words — "changelog", "write the changelog", "changelog eintragen", "Changelog schreiben", "Changelog prüfen", "release notes".
---

# Changelog

Merchants read these files, not developers. Both `CHANGELOG_de-DE.md` and
`CHANGELOG_en-GB.md` get the same entries, in the same order, under `# Unreleased`.

## Rules

- **Only what a merchant was waiting for.** No entry for refactoring, tests, tooling, CI,
  internal renames, or a fix that only surfaced while writing tests.
- **One short sentence per entry**, about fifteen words: the effect for the merchant. No root
  cause, class, file, API field, Shopware internals or ticket number. No "so that …".
- **One behaviour, one entry.** Several bullets about the same thing are merged.
- **Labels in this order:** `Hinzugefügt`/`Added`, `Geändert`/`Changed`, `Behoben`/`Fixed`.
- **No wall of `Behoben`/`Fixed`.** Where new or changed behaviour honestly fits, label it
  that way and word it so. "wieder" / "correctly again" stays a fix.
- **Both languages read native**, not translated word by word.

## Check before hand-over

Diff the changelogs against the code diff: missing entry, entry that should not exist,
de/en drift, wrong section. For every problem give the replacement sentence in both
languages.
