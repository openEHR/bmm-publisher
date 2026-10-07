# Claude Code: project instructions

Follow **[AGENTS.md](../AGENTS.md)**, the canonical reference for layout, architecture, standards, CLI commands, commit style, and workflows.

**Critical:** there is no host PHP. Run PHP, Composer, PHPUnit, and PHPStan inside the dev container with `make ci`, `make sh`, `make install`, or `docker compose -f .docker/docker-compose.yml run --rm app composer …`.

**Before you finish:** `make ci` must pass. If you changed a writer, formatter, or template, also run `make publish-all` and include the regenerated `output/`. Never edit `output/` by hand.

Details on demand:

- [Documentation index](../docs/README.md)
- [AI workflow](../docs/ai-workflow.md): guardrails, verification, regenerating `output/`
- [Architecture](../docs/architecture.md): pipeline, writers, key patterns
- [Development](../docs/development.md): Composer scripts, tooling, Docker images
- [Releases](../docs/releases.md): version bump and release process
