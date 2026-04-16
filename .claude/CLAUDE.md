# Claude Code – project instructions

This repo follows **[AGENTS.md](AGENTS.md)** for structure, PHP standards (PSR-12, PHPStan level 8, PHPUnit 12), tooling locations (`tests/` for config), commit style, and escalation.

- **Setup and scripts**: [README.md](README.md) and `composer.json` scripts (e.g. `composer ci`).
- **Cursor**: [`.cursor/rules/`](.cursor/rules/) — `project-context.mdc` is always applied; PHP and testing rules attach by glob.
- **Docker**: `.docker/docker-compose.yml` or `Makefile` from the repo root.

Do not add long-form project docs at the repo root; use `docs/` when you create documentation (see AGENTS.md).

## Version bumps

When releasing a new version, **both** of these must be updated in the same commit/PR:

1. **`CHANGELOG.md`** — move the `[Unreleased]` items into a new versioned section and update the comparison links at the bottom.
2. **`bin/bmm-publisher`** — update the version string in `new Application('bmm-publisher', '<version>')` to match.
