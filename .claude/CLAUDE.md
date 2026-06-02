# Claude Code – project instructions

Follow **[AGENTS.md](../AGENTS.md)** — the canonical reference for layout, architecture, standards, CLI commands, commit style, and workflows.

**Critical:** PHP / Composer / PHPUnit / PHPStan run **inside the dev container** only — there is no host PHP. Use `make ci`, `make sh`, `make install`, or `docker compose -f .docker/docker-compose.yml run --rm app composer …`.

Deeper detail on demand:

- AI agent working process (guardrails, verification, regenerate `output/`) — [docs/ai-workflow.md](../docs/ai-workflow.md)
- Architecture (pipeline, writers, key patterns) — [docs/architecture.md](../docs/architecture.md)
- Tooling, Composer scripts, Docker images — [docs/development.md](../docs/development.md)
- Version bump & release process — [docs/releases.md](../docs/releases.md)
