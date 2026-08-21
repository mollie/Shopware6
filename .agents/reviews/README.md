# How to review

Shared protocol for every checklist in this directory. Read this plus the one checklist you
were pointed at.

- **Get the diff yourself** if it was not handed to you: `git diff`, or
  `git diff <base>...HEAD` for a branch.
- **Read the changed files in full and find the callers.** A diff alone cannot be judged.
- **You review. You do not edit.** Never run test suites or static analysis — see `AGENTS.md`
  section 5.
- **Do not report what PHPStan, CS Fixer or ESLint catch on their own.** The human runs those.
- **Output:** one line per finding, `path:line — problem — what to do`. Worst first. No
  praise, no summary of what the code does, no invented findings. If there is nothing, answer
  `nothing`.
