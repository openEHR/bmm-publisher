# AGENTS.md

This repository is a **PHP CLI tool** that reads **openEHR BMM (Basic Meta-Model) schemas** and publishes class definitions as **AsciiDoc**, **PlantUML**, and **YAML** for the [openEHR specifications website](https://specifications.openehr.org/).

Use this file as the **primary reference** for agents, automation, and contribution expectations. See also **README.md** for install/commands and **CONTRIBUTING.md** for PR workflow.

## Purpose

- **CLI tool**: Reads BMM 2.4 JSON schemas (P_BMM format) and generates output in multiple formats for use in the openEHR specifications documentation pipeline.
- **Output formats**: AsciiDoc (for specification pages), PlantUML (for class diagrams), YAML (for structured data), split per-type JSON.
- **Input**: P_BMM JSON schema files (see `resources/BMM-JSON/`).
- **Dependency**: Uses `cadasto/openehr-bmm` as the BMM model library (provides `BmmSchema`, classes, properties, types, etc.).

## Layout and ownership

| Area | Responsibility |
|------|----------------|
| `bin/bmm-publisher` | CLI entry point (Symfony Console Application) |
| `bin/Command/` | Console commands; PSR-4 namespace `OpenEHR\BmmPublisher\Console\` |
| `src/` | Application source; PSR-4 namespace `OpenEHR\BmmPublisher\` |
| `resources/` | **Input**: openEHR BMM schemas in P_BMM JSON format (`.bmm.json` files) |
| `output/` | **Generated** (gitignored): all writer output |
| `output/Adoc/` | AsciiDoc tables (definitions, effective, tabs, BMM JSON blocks, PlantUML blocks) |
| `output/PlantUML/` | PlantUML `.puml` diagram files |
| `output/BMM-YAML/` | YAML serialisations of BMM schemas |
| `output/BMM-JSON-development-types/` | Per-type split JSON files grouped by component (AM, RM, BASE, LANG, TERM) |
| `tests/` | Unit/integration tests **and** tool config: `phpunit.xml`, `phpstan.neon`, `phpcs.xml`, `rector.php`, optional `phpstan-baseline.neon` |
| `docs/` | Project documentation (guides, architecture). **Place new docs here**, not at the repo root except README/CONTRIBUTING/CODE_OF_CONDUCT/SECURITY. |
| `.claude/` | Claude Code project instructions (`CLAUDE.md`). |
| `.cursor/rules/` | Cursor rules (`project-context.mdc`, `commit-messages.mdc`, PHP/testing rules). |
| `.github/` | CI workflow, release workflow (GitHub Release + Docker image to GHCR), Dependabot, issue/PR templates, Copilot instructions. |
| `.docker/` | Multistage Dockerfile (PHP 8.5-cli Alpine): `production` target (CI/release, no xdebug) and `development` target (xdebug, Composer). Docker-compose targets `development`. |
| `README.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md` | Root-level process and team docs |

Coding standards and quality checks are defined by config files in `tests/` and the Composer scripts in `composer.json`.

> **Note:** This is a CLI project, **not** a Composer library. The deliverable is a Docker image published to **GitHub Container Registry** (`ghcr.io/openehr/bmm-publisher`), not a Packagist package.

## CLI commands

The entry point `bin/bmm-publisher` provides these Symfony Console commands:

| Command | Aliases | Description |
|---------|---------|-------------|
| `asciidoc` | `adoc` | Convert BMM JSON schemas to AsciiDoc tables |
| `plantuml` | `uml`, `puml` | Convert BMM JSON schemas to PlantUML diagrams |
| `yaml` | | Convert BMM JSON schemas to YAML format |
| `split-json` | | Split latest BMM JSON of each component into per-type files |

Commands accept schema name(s) as arguments (without `.bmm.json` extension), or `all` to process every schema in `resources/BMM-JSON/`.

## Architecture

```
bin/bmm-publisher  (Symfony Console Application)
  └── Command  →  BmmSchemaCollection  →  Writer

BmmSchemaCollection: loads .bmm.json → BmmSchema objects (via cadasto/openehr-bmm)
                     provides cross-schema class/package lookups

Writers (callable classes, receive BmmSchemaCollection, delegate to Formatters):
  ├── Asciidoc      → AsciidocDefinition, AsciidocEffective, AsciidocTab,
  │                   AsciidocBmmJson, AsciidocPlantUml
  ├── PlantUml      → Formatter\PlantUml
  ├── BmmYaml       (uses Symfony Yaml component)
  └── BmmJsonSplit  (per-type JSON with openEHR spec URLs)
```

**Key patterns**:
- **BmmSchemaCollection** loads BMM JSON files, provides iteration over schemas, and cross-schema lookups (`getClass()`, `getClassPackageQName()`).
- **Writers** are standalone callable classes (`__invoke()`), each receiving `BmmSchemaCollection` via constructor.
- **Filesystem** helper provides `assureDir()` and `writeFile()` used by all writers.
- **Formatters** are readonly classes that transform BMM model objects into output strings.
- **Logging**: PSR-3 via Symfony `ConsoleLogger`. Created in commands, injected into `BmmSchemaCollection`, accessed by writers via `$schemas->logger`. Progress at `notice` level (shown with `-v`), detail at `info` (`-vv`).
- **`ResourcesDir`** resolves the input schemas path (hardcoded to `{cwd}/resources`).
- **`OutputDir`** resolves the output path: override via `BMM_OUTPUT_DIR` env var (for Docker), defaults to `{cwd}/output`.

## Documentation

### Development

- [docs/development.md](docs/development.md) — development workflow and tooling details (**Composer / PHP run in Docker** — use `make ci`, `make install`, or `docker compose -f .docker/docker-compose.yml run --rm app composer …`)
- [docs/releases.md](docs/releases.md) — release process, tagging, and Docker image publishing
- [docs/README.md](docs/README.md) — docs directory index

## Standards (for contributors and agents)

- **Style**: PSR-12 (PHPCS; config in `tests/phpcs.xml`).
- **Static analysis**: PHPStan level 8 (`tests/phpstan.neon`).
- **Tests**: PHPUnit 12 (`tests/phpunit.xml`). Use `declare(strict_types=1);` and type hints.
- **Refactoring**: Rector (config in `tests/rector.php`). Run **`composer rector`** inside the dev container (`make sh` or `docker compose … run --rm app composer rector`); Rector is not part of CI.
- **Branching**: `main` is releasable; use feature/fix branches and run **`make ci`** (or the equivalent Docker `composer ci`) before opening a PR.
- **Commit messages**: Conventional style — **`type: imperative subject`**, under ~72 characters, optional body after a blank line. Types: `feat`, `fix`, `chore`, `docs`, `refactor`, `test`. See [.cursor/rules/commit-messages.mdc](.cursor/rules/commit-messages.mdc) for full detail.

Keep PHPUnit, PHPStan, PHPCS, and Rector config under `tests/` so the project root stays minimal.

**PHP version**: Development uses PHP 8.5 in Docker; `composer.json` requires `^8.4`. CI runs the same checks on **PHP 8.4 and 8.5** (both must pass). The release workflow runs on PHP 8.4.

## IDE and agent integration

- **Claude Code**: Project instructions in **`.claude/CLAUDE.md`**; this file is the authoritative reference it points to.
- **Cursor**: Behavioural rules in **`.cursor/rules/`** (always-applied `project-context.mdc`, plus glob-attached PHP and testing rules).
- **GitHub Copilot**: Instructions in **`.github/copilot-instructions.md`**; delegates to this file.

## Escalation and triage

1. Open or assign a GitHub issue with the `triage` label.
2. For release blockers or security: follow CONTRIBUTING.md.

## How to get help

- **Usage or design**: GitHub Discussion or Issue.
- **Bug**: Use the bug report issue form.
- **Feature**: Use the feature request issue form.
- **Security**: Do **not** open a public issue; follow the security reporting instructions in `CONTRIBUTING.md`.
