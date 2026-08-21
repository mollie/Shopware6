# Ticket Workflow — Maintainers

Internal process for the maintainers. **External contributors:** fork,
branch however you like, follow `AGENTS.md` sections 2 to 7.

Only the git mechanics live here. Everything else is `AGENTS.md`.

## Ticket data — one question

Ask once, for all three: ticket number (`PISHPS-123`, or `NTR`), your initials (lowercase),
and a short summary for the branch name.

## Branch

```bash
git checkout -b users/<initials>/<ticket>-<summary>
```

`users/vm/PISHPS-123-add-vipps-payment` · `users/vm/NTR-fix-refund-cap`

On failure: report the exact error and stop. Do not repair the git state.

## Commit — only when asked

```bash
git add <specific files>          # never `git add .`
git commit -m "<TICKET>: <short description>"
```

`PISHPS-123: add vipps payment method` · `NTR: fix refund cap for net orders`

One commit per logical change. Do not push or open a pull request unless asked.
