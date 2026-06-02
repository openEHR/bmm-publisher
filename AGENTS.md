# AGENTS.md

This repository is a **PHP CLI tool** that reads **openEHR BMM (Basic Meta-Model) schemas** and publishes class definitions as **AsciiDoc**, **PlantUML**, and **YAML** for the [openEHR specifications website](https://specifications.openehr.org/).

Use this file as the **primary reference** for agents, automation, and contribution expectations. See also **README.md** for install/commands and **CONTRIBUTING.md** for PR workflow.

> **No host PHP.** Run Composer / PHPUnit / PHPStan / PHPCS and the CLI **inside the dev container** — `make ci`, `make sh`, `make install`, or `docker compose -f .docker/docker-compose.yml run --rm app composer …`. See [docs/ai-workflow.md](docs/ai-workflow.md).

## Purpose

- **CLI tool**: Reads BMM 2.4 JSON schemas (P_BMM format) and generates output in multiple formats for use in the openEHR specifications documentation pipeline.
- **Output formats**: AsciiDoc (for specification pages), PlantUML (for class diagrams), YAML (for structured data), split per-type JSON.
- **Input**: P_BMM JSON schema files (see `resources/`).
- **Dependency**: Uses `cadasto/openehr-bmm` as the BMM model library (provides `BmmSchema`, classes, properties, types, etc.).

## Layout and ownership

| Area | Responsibility |
|------|----------------|
| `bin/bmm-publisher` | CLI entry point (Symfony Console Application) |
| `bin/Command/` | Console commands; PSR-4 namespace `OpenEHR\BmmPublisher\Console\` |
| `src/` | Application source; PSR-4 namespace `OpenEHR\BmmPublisher\` |
| `resources/` | **Input**: openEHR BMM schemas in P_BMM JSON format (`.bmm.json` files) |
| `output/` | **Generated** writer output (committed; CI's `verify-output` job re-runs `make publish-all` and fails on `git diff -- output/`) |
| `output/Adoc/` | AsciiDoc tables (definitions, effective, tabs, BMM JSON blocks); `plantUML/{classes,packages}/` holds only the `.puml` source — UML image macros are inlined directly into the tabs partials under `classes/<name>.adoc`; rendered diagrams live under `images/uml/{classes,diagrams}/` |
| `output/PlantUML/` | PlantUML `.puml` diagram files |
| `output/BMM-YAML/` | YAML serialisations of BMM schemas |
| `output/BMM-JSON-development-types/` | Per-type split JSON grouped by component (`AM`, `RM`, `BASE`, `LANG`, `TERM`), plus generation-suffixed dirs for same-id variants (`AM2`, `LANG-bmm3`) |
| `tests/` | Unit/integration tests **and** tool config: `phpunit.xml`, `phpstan.neon`, `phpcs.xml`, `rector.php`, optional `phpstan-baseline.neon` |
| `docs/` | Project documentation (guides, architecture). **Place new docs here**, not at the repo root except README/CONTRIBUTING/CODE_OF_CONDUCT/SECURITY. |
| `.claude/` | Claude Code project instructions (`CLAUDE.md`). |
| `.cursor/rules/` | Cursor rules (`project-context.mdc`, `commit-messages.mdc`, PHP/testing rules). |
| `.github/` | CI workflow, release workflow (GitHub Release + Docker image to GHCR), Dependabot, issue/PR templates, Copilot instructions. |
| `.docker/` | Multistage Dockerfile (PHP 8.5-cli Alpine + Alpine `plantuml` package, which transitively pulls in OpenJDK + Graphviz + DejaVu fonts): `production` target (CI/release, no xdebug) and `development` target (xdebug, Composer, git). Docker-compose targets `development`. |
| `README.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md` | Root-level process and team docs |

Coding standards and quality checks are defined by config files in `tests/` and the Composer scripts in `composer.json`.

> **Note:** This is a CLI project, **not** a Composer library. The deliverable is a Docker image published to **GitHub Container Registry** (`ghcr.io/openehr/bmm-publisher`), not a Packagist package.

## CLI commands

The entry point `bin/bmm-publisher` provides these Symfony Console commands:

| Command | Aliases | Description |
|---------|---------|-------------|
| `asciidoc` | `adoc` | Convert BMM JSON schemas to AsciiDoc tables. Atomic: the writer emits `.puml` source plus the tabs partial with the UML image macro inlined, the in-image `plantuml` CLI renders `.svg`, and `EmbedSvg` sanitises and publishes each SVG under `images/uml/classes/` or `images/uml/diagrams/`. |
| `legacy-adoc` | | Generate the legacy `docs/UML/classes` layout — flat `org.openehr.<schema>.<class>.adoc` class-definition tables only (no diagrams/effective/YAML). Supports `-o <dir>` for an explicit output directory. |
| `plantuml` | `uml`, `puml` | Convert BMM JSON schemas to PlantUML diagrams (standalone tree under `output/PlantUML/`) |
| `embed-svg` | | Re-run only the SVG sanitise + publish step against existing `.svg` files (debugging / surgical re-runs). |
| `yaml` | | Convert BMM JSON schemas to YAML format |
| `split-json` | | Split latest BMM JSON of each component into per-type files |

Commands accept schema id(s) (without `.bmm.json` extension) or `.bmm.json` path(s) as arguments, or `all` to process every schema in `resources/`. `asciidoc`, `legacy-adoc`, and `plantuml` also accept repeatable **`-d <schema>`** dependencies — loaded for cross-reference resolution but **not** exported.

## Architecture

```
bin/bmm-publisher  (Symfony Console Application)
  └── Command  →  BmmSchemaCollection  →  Writer  →  Formatter
```

- **BmmSchemaCollection** loads `.bmm.json` → `BmmSchema` objects (via `cadasto/openehr-bmm`) and provides cross-schema lookups.
- **Writers** are standalone callable classes (`__invoke()`), each receiving the collection: `Asciidoc`, `EmbedSvg`, `PlantUml`, `BmmYaml`, `BmmJsonSplit`. **Formatters** transform model objects into output strings.
- The `asciidoc` command is a **self-contained pipeline** (writer → bundled `plantuml` CLI → `EmbedSvg`) in one container run.
- **Gotcha — schema-id collisions**: the collection keys schemas by `getSchemaId()`, so same-id inputs (e.g. `openehr_lang_1.1.0` and the `…-bmm3` overlay) overwrite each other; writers that must emit both process each input separately and disambiguate output.

See **[docs/architecture.md](docs/architecture.md)** for the full pipeline diagram, SVG/Antora details, and the complete key-patterns list.

## Documentation

### Development

- [docs/architecture.md](docs/architecture.md) — full pipeline diagram, the atomic `asciidoc` SVG/Antora flow, and the complete key-patterns list
- [docs/development.md](docs/development.md) — development workflow and tooling details (**Composer / PHP run in Docker** — use `make ci`, `make install`, or `docker compose -f .docker/docker-compose.yml run --rm app composer …`)
- [docs/ai-workflow.md](docs/ai-workflow.md) — AI agent working process: container-only tooling, editing guardrails, verification checklist, entrypoint map
- [docs/install.md](docs/install.md) — installing and running the published Docker image (invocation, volumes, env overrides, host-user mapping)
- [docs/releases.md](docs/releases.md) — version bump (CHANGELOG + `bin/bmm-publisher`), release process, tagging, and Docker image publishing
- [docs/README.md](docs/README.md) — docs directory index

## Standards (for contributors and agents)

- **Style**: PSR-12 (PHPCS; config in `tests/phpcs.xml`).
- **Static analysis**: PHPStan level 8 (`tests/phpstan.neon`).
- **Tests**: PHPUnit 12 (`tests/phpunit.xml`). Use `declare(strict_types=1);` and type hints.
- **Refactoring**: Rector (config in `tests/rector.php`). Run **`composer rector`** inside the dev container (`make sh` or `docker compose … run --rm app composer rector`); Rector is not part of CI.
- **Branching**: `main` is releasable; use feature/fix branches and run **`make ci`** (or the equivalent Docker `composer ci`) before opening a PR.
- **Commit messages**: Conventional style — **`type: imperative subject`**, imperative mood (`add`, `fix` — not `added`/`Enhances…`), subject under ~72 characters, optional body after a blank line. Types: `feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `ci`. One-line hint when drafting: `conventional commit, <72 chars, feat/refactor/fix`.
  - **Good:** `refactor: map generic params in BmmGenericType::fromArray`
  - **Bad:** `Enhance BmmGenericType::fromArray method to process generic parameters, ensuring proper conversion…`
- **CHANGELOG entries**: keep each bullet to **1 sentence** (≈25 words). Lead with the user-visible change in **bold**; a brief parenthetical or em-dash clause is fine for the *why*; defer code paths, class renames, and rationale to commit messages and PR descriptions. Compare 0.4.0–0.7.0 entries for the target density.

Keep PHPUnit, PHPStan, PHPCS, and Rector config under `tests/` so the project root stays minimal.

**PHP version**: PHP 8.5+ across the board — `composer.json` requires `^8.5`, both the Docker dev/prod images and the CI / release workflows run PHP 8.5.

## IDE and agent integration

Each tool (Claude Code, Cursor, GitHub Copilot, JetBrains Junie) reads a minimal entrypoint that defers to this file and to **[docs/ai-workflow.md](docs/ai-workflow.md)** — the agent working process (container-only tooling, editing guardrails, verification checklist, entrypoint map).

## Escalation and triage

1. Open or assign a GitHub issue with the `triage` label.
2. For release blockers or security: follow CONTRIBUTING.md.

## How to get help

- **Usage or design**: GitHub Discussion or Issue.
- **Bug**: Use the bug report issue form.
- **Feature**: Use the feature request issue form.
- **Security**: Do **not** open a public issue; follow the security reporting instructions in `CONTRIBUTING.md`.
