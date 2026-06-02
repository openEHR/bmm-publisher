# AI agent workflow

How AI assistants (Claude Code, Cursor, GitHub Copilot, JetBrains Junie) should work in this repository. **[AGENTS.md](../AGENTS.md)** remains the canonical reference for layout, architecture, standards, and CLI commands; this file covers the *working process*.

## Run everything through the dev container

This repo has **no host PHP requirement**. Drive Composer, PHPUnit, PHPStan, PHPCS, Rector, and the `plantuml` CLI through the dev container — never assume `php`/`composer`/`phpunit`/`plantuml` exist on the host PATH:

- `make ci` — full quality gate (lint, PHPCS, PHPStan, PHPUnit). Run before submitting.
- `make install` — `composer install` in the container.
- `make sh` — interactive shell for ad-hoc commands.
- `make publish-all` — regenerate everything under `output/`.
- `docker compose -f .docker/docker-compose.yml run --rm app composer <script>` — fallback for any Composer script (e.g. `composer rector`).

Full command reference: **[development.md](development.md)**.

## Editing guardrails

- **`output/` is committed but generated.** Never hand-edit files under `output/`; regenerate via `make publish-all`. CI's `verify-output` job fails on drift.
- **`resources/*.bmm.json`** is upstream input — don't modify unless the task explicitly says so.
- **Tool config lives in `tests/`** (`phpunit.xml`, `phpstan.neon`, `phpcs.xml`, `rector.php`) — keep it there, not at the repo root.
- Mirror existing code style: PSR-12, `declare(strict_types=1);`, full type hints, PHPStan level 8 green. Match the surrounding comment density — don't add comments where neighbouring code has none.

## Verification checklist before submitting

1. `make ci` is green (or document why a subset was run for trivial changes).
2. If writers, formatters, or templates changed: run `make publish-all` and commit the regenerated files under `output/`.
3. New tests for fixed bugs / non-trivial features, per AGENTS.md "Standards".
4. CHANGELOG entry follows the 1-sentence / bold-lede convention (see AGENTS.md "Standards").

## Commits

Conventional commits per **[AGENTS.md](../AGENTS.md)**: `type: imperative subject` (`feat`/`fix`/`chore`/`docs`/`refactor`/`test`/`ci`), subject < 72 chars. Commit only when explicitly asked. Tool-specific co-author trailers (e.g. Junie's `Co-authored-by: Junie <junie@jetbrains.com>`) are configured in each tool's own entrypoint file.

## Agent entrypoint files

Each tool reads a minimal entrypoint that defers to AGENTS.md and to this file:

| Tool | Entrypoint |
|------|-----------|
| Claude Code | `.claude/CLAUDE.md` |
| Cursor | `.cursor/rules/` (always-applied `project-context.mdc` + `commit-messages.mdc`; glob-attached PHP and testing rules) |
| GitHub Copilot | `.github/copilot-instructions.md` |
