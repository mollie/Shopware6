---
name: update-shopware-versions
description: Bring the Shopware version matrix of the CI, PR and nightly pipelines up to date with the versions Shopware has actually released, and check every version against the available dockware/shopware images. Use when asked to update the Shopware versions, the pipeline matrix or the test matrix, to check for new Shopware releases, or when a pipeline fails because a dockware image is missing — "update shopware versions", "neue Shopware Version", "Matrix aktualisieren", "check for new Shopware releases", "gibt es neue Shopware Releases".
---

# Update the Shopware version matrix

Compares what Shopware has released against what `dockware/shopware` offers as an image, and
rewrites the version matrix in three workflow files. Nothing else — this skill does not run a
pipeline, does not commit, and does not open a ticket.

The matrix lives **only** in these three files:

- `.github/workflows/pr_pipe.yml` — job `e2e`
- `.github/workflows/ci_pipe.yml` — job `e2e`
- `.github/workflows/nightly_pipe.yml` — job `e2e`

Do not touch `compatibility_pipe.yml` or `e2e_playground.yml`: they take the version as a
workflow input. Do not touch `.github/actions/run-e2e/action.yml`.

## 1. Read the released Shopware versions

```bash
git ls-remote --tags https://github.com/shopware/shopware \
  | sed 's|.*refs/tags/||; s|\^{}||' | sort -u \
  | grep -E '^v6\.[0-9]+\.[0-9]+\.[0-9]+$' | sed 's|^v||' | sort -V
```

`git ls-remote` instead of the GitHub website or API: it is complete, needs no token, and has
no rate limit. There is no `gh` CLI on this machine, and `curl` is on the forbidden list in
section 5 of `AGENTS.md`.

The anchored `grep` is what drops `-rc1`, `-alpha` and `-dev` tags. A pre-release never goes
into a matrix.

Version words, as Shopware counts them — in `6.7.13.1`:

| Segment | Name here |
|---|---|
| `6.7` | major |
| `13` | minor |
| `1` | patch |

**The supported window** is the constraint in `composer.json` (`shopware/core`), today
`>=6.5.8.0 <6.8`. Everything below `6.5.8.0` is ignored, so the 6.5 line is `6.5.8.x` only —
never `6.5.7.x`. For the upper bound see section 5.

## 2. Read the available dockware images

The matrix runs `dockware/shopware:<version>`, and dockware lags behind Shopware. Read the
tags from Docker Hub with WebFetch, one call per major:

```
https://hub.docker.com/v2/repositories/dockware/shopware/tags?page_size=100&name=6.7.
```

Ask for every tag name in `results` that does **not** end in `-amd64` or `-arm64`, plus the
`count` field. Sanity check the answer: dockware publishes three tags per version, so
`count` should be roughly three times the number of names you got back. If it is far off, the
page was truncated — fetch `&page=2` as well.

Before you report a version as missing (section 4), confirm it one more time against the
single-tag endpoint, because a summarised list can drop a line:

```
https://hub.docker.com/v2/repositories/dockware/shopware/tags/6.7.14.0
```

JSON with a `name` field → the image exists. `object not found` → it does not.

What dockware has today, so you can spot a nonsense answer: `6.5.8.x` from `.18`, `6.6.10.x`
only (no 6.6.0–6.6.9 at all), `6.7.x` from minor `.2` upwards (no 6.7.0, no 6.7.1). Those gaps
are dockware gaps, not oversights in the matrix.

## 3. Build the two matrices

**PR and CI — `pr_pipe.yml` and `ci_pipe.yml`, identical content, exactly one entry per major:**

The newest patch of the newest minor of that major, and it must exist as a dockware image. If
the newest Shopware version for a major has no image, take the newest one that does and carry
that version into section 4. Today that is three entries: 6.7, 6.6, 6.5.8.

**Nightly — `nightly_pipe.yml`, every minor of every supported major:**

For each minor, the newest patch that exists as a dockware image. A minor with no image at all
is **skipped silently** — no ticket, no comment, no placeholder. That is why 6.6.0 to 6.6.9 and
6.7.0 to 6.7.1 are absent, and it is correct.

**Both matrices:**

- Order descending, newest first: 6.7 block, then 6.6, then 6.5.8.
- `image: 'dockware/shopware'` on every entry.
- `php: '8.2'` on **every** entry, in all three files. 8.2 is the plugin's own minimum
  (`composer.json` → `"php": ">=8.2"`) and the version every supported Shopware runs on. Some
  dockware images ship a `composer.lock` pinned to a higher platform requirement, which the
  `run-e2e` action already works around — that is not a reason to raise a job to 8.3 or 8.4.
  If you find an `8.4` left in a matrix, set it to `8.2` while you are in the file.
- Quote the version and the PHP version, keep the existing three-line shape:

```yaml
          - shopware: '6.7.13.1'
            php: '8.2'
            image: 'dockware/shopware'
```

Only the `include:` list changes. Leave every other line of the job alone — `fail-fast`, the
steps, the comments.

If the recomputed matrix equals what is already in the file, say so and change nothing. That
is the normal outcome; it means nothing was released since the last run.

## 4. Missing images — tell the developer, never open the ticket

Only the PR/CI versions from section 3 are worth a ticket: a nightly gap is ignored by design.

When Shopware's newest version for a major has no dockware image, name it and stop there:

> 6.7.14.0 is released but has no `dockware/shopware` image. Please open an issue at
> https://github.com/dockware/shopware/issues/new — the matrix uses 6.7.13.1 until then.

**Do not open the issue yourself** — not with `gh`, not in Jira, not by any other route. One
version per ticket, and the developer decides whether it is worth asking for.

## 5. A new major appeared

When Shopware releases a version above the upper bound in `composer.json` — 6.8.0.0 today —
take it into all three matrices like any other major, and lift the bound in `composer.json`.
It appears **four** times, all of them `>=6.5.8.0 <6.8`:

`shopware/core`, and under `require-dev` `shopware/storefront`, `shopware/administration`,
`shopware/elasticsearch`. Change only the upper bound: `<6.8` → `<6.9`.

Leave `config/.shopware-extension.yml` alone; its `shopwareVersionConstraint` is a lower bound.

Then say it plainly in the report: the plugin code has not been adapted to the new major, the
new jobs will very likely be red, and that red is the point — it is the compatibility work
becoming visible, not a reason to revert the matrix.

## 6. Report it, one line

> Nothing to do — 6.7.13.1 is the newest and already in all three matrices.

or

> 6.7.14.1 added to `pr_pipe.yml`, `ci_pipe.yml`, `nightly_pipe.yml` (replaces 6.7.13.1 in PR/CI).

Do not run the pipelines, do not commit, tag or push (section 5 and section 8 of `AGENTS.md`).
Naming the branch and opening the pull request is the developer's call.
