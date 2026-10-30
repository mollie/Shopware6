# AI setup

`AGENTS.md` holds the rules every AI tool reads first. Everything else is loaded on demand.

| Path | Content |
|---|---|
| `.agents/skills/<name>/SKILL.md` | Skills in the [Agent Skills](https://agentskills.io) format: `review`, `changelog`, `record-correction`, `payment-methods`, `increase-version`, `update-shopware-versions` |
| `.agents/guidelines/php.md` | PHP and Shopware coding standard |
| `.agents/guidelines/testing.md` | Test rules |
| `.agents/guidelines/domain.md` | Mollie and Shopware behaviour the code depends on |
| `.agents/workflows/workflow-ticket.md` | Branch and commit format for maintainers |

Codex, OpenCode, Cursor, Copilot and Gemini CLI read `.agents/skills/` natively. Claude Code
reads `.claude/skills/`, which is a symlink to `.agents/skills/`. Any other tool: read the
`SKILL.md` yourself.

Every skill description ends with trigger words in English and German, so the tool picks the
skill for a matching request.

When the AI repeats the same mistake, the `record-correction` skill sharpens the rule in the
file that should have caught it.
