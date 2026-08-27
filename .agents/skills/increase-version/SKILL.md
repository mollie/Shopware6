---
name: increase-version
description: Set the release version of the Mollie plugin before it goes to internal QA. Bumps composer.json and the MolliePayments::PLUGIN_VERSION constant, and turns the "# Unreleased" changelog section into the new version heading in both languages. Use when asked to increase, bump or set the plugin version, to prepare a release or hand over to QA — "increase version", "bump the version", "release 5.5.0", "prepare the next release", "Version erhöhen", "neue Version".
---

# Increase the plugin version

Run this once per release cycle, right before the plugin goes to internal QA. It writes one
version number into four files and closes the open changelog section. Nothing else — this
skill does not build, tag, commit or release.

## 1. Read the current version, then agree on the new one

The current version lives in two places and they must already agree:

- `composer.json` → `"version": "v5.4.0"` (**with** the `v` prefix)
- `src/MolliePayments.php` → `public const PLUGIN_VERSION = '5.4.0';` (**without** the prefix)

If the two disagree, stop and ask which one is right. Do not guess which is stale.

If the developer already named the version ("release 5.5.0"), take it and skip the question.
Otherwise ask **once**, offering the computed numbers rather than the words "minor" and
"major" — from 5.4.0 the choices are:

| Release | New version | When |
|---|---|---|
| Minor — **the normal case** | 5.5.0 | the usual QA cycle, offer this first |
| Hotfix / patch | 5.4.1 | a fix on top of a version already released |
| Major | 6.0.0 | the plugin was reworked, breaking for merchants |

One question, then wait. Do not start editing on the assumption it is a minor.

## 2. Check there is something to release

Both changelogs must have entries under `# Unreleased`, and **the same number of them**.

- Empty section → stop and say so. A version with no changelog entry is a mistake, not a
  release.
- de and en differ → name the entry that is missing on one side and stop. Do not translate
  the missing one over yourself; the developer decides whether it belongs in the release.

## 3. Write the version into the four files

Only these four files carry the version:

1. `composer.json` — `"version": "v<new>"`, keep the `v`.
2. `src/MolliePayments.php` — `public const PLUGIN_VERSION = '<new>';`, no `v`.
3. `CHANGELOG_de-DE.md`
4. `CHANGELOG_en-GB.md`

In both changelogs, insert a blank line and the new heading directly under `# Unreleased`, so
the entries that were open now sit under the released version:

```markdown
# Unreleased

# 5.5.0
- Hinzugefügt: …
- Geändert: …
```

`# Unreleased` stays as the first line and is left empty — the next change goes there. The
entries themselves are not touched: not reordered, not reworded, not merged. A blank line
before every `# <version>` heading is the existing format; keep it.

Nothing else needs editing, and editing it would be wrong:

- the `Makefile` reads `PLUGIN_VERSION` out of `composer.json`,
- the tests reference `MolliePayments::PLUGIN_VERSION` symbolically,
- the `package.json` files under `src/Resources/app/` are pinned at `1.0.0` and track nothing.

## 4. Offer a changelog pass, do not run it

The released entries were written across many pull requests and each was only ever reviewed
on its own. If the new section is long, the merchant-facing whole is worth one look with
`.agents/skills/review-changelog/`. **Offer it and stop** — the developer decides, because a
review that rewords entries changes what merchants read on release day.

## 5. Report it, one line

> 5.5.0 — `composer.json`, `src/MolliePayments.php`, both changelogs. 14 entries released.

Do not commit, tag or push, and never run `make release` or `make build` (section 5 of
`AGENTS.md`). Naming the branch or the commit is the developer's call.
