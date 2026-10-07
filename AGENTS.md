# AGENTS.md

This repository is a **PHP CLI tool** that reads **openEHR BMM (Basic Meta-Model) schemas** and publishes class definitions as **AsciiDoc**, **PlantUML**, **YAML**, **ODIN**, and per-type **JSON** for the [openEHR specifications website](https://specifications.openehr.org/).

Use this file as the **primary reference** for agents, automation, and contribution expectations. See **README.md** for install and commands, and **CONTRIBUTING.md** for the PR workflow.

> **No host PHP.** Run Composer, PHPUnit, PHPStan, PHPCS, and the CLI **inside the dev container**: `make ci`, `make sh`, `make install`, or `docker compose -f .docker/docker-compose.yml run --rm app composer …`. See [docs/ai-workflow.md](docs/ai-workflow.md).

## Purpose

- **CLI tool**: reads BMM 2.4 JSON schemas (P_BMM format) and generates output in multiple formats for the openEHR specifications documentation pipeline.
- **Output formats**: AsciiDoc (specification pages), PlantUML (class diagrams), YAML (structured data), ODIN `.bmm` schemas, split per-type JSON.
- **Input**: P_BMM JSON schema files in `resources/`.
- **Dependency**: `cadasto/openehr-bmm` is the BMM model library (`BmmSchema`, classes, properties, types, and so on).

## Common tasks

| Task | Command |
|------|---------|
| Run the full quality gate (lint, PHPCS, PHPStan, PHPUnit) | `make ci` |
| Run one test class | `docker compose -f .docker/docker-compose.yml run --rm app composer test -- --filter <ClassName>` |
| Run the CLI | `make sh`, then `./bin/bmm-publisher <command> …` |
| Regenerate all committed output | `make publish-all` |
| Regenerate one format | `make adoc`, `make puml`, `make yaml`, `make split-json`, `make legacy-adoc`, or `make odin` |

## Guardrails

- **`output/` is generated and committed.** Never edit it by hand. After changing a writer, formatter, or template, run `make publish-all` and commit the result. CI's `verify-output` job re-runs `make publish-all` and fails on any `git diff -- output/`.
- **`resources/*.bmm.json` is upstream input.** Do not modify it unless the task says so.
- **Version bumps touch two files.** The version string in `bin/bmm-publisher` must match the newest `CHANGELOG.md` section ([docs/releases.md](docs/releases.md)).
- **Commit only when asked.**

## Layout and ownership

| Area | Responsibility |
|------|----------------|
| `bin/bmm-publisher` | CLI entry point (Symfony Console Application) |
| `bin/Command/` | Console commands; PSR-4 namespace `OpenEHR\BmmPublisher\Console\` |
| `src/` | Application source; PSR-4 namespace `OpenEHR\BmmPublisher\` |
| `resources/` | **Input**: openEHR BMM schemas in P_BMM JSON format (`.bmm.json` files) |
| `output/` | **Generated** writer output, committed (see Guardrails) |
| `output/Adoc/` | AsciiDoc tables (definitions, effective, tabs, BMM JSON blocks). `plantUML/{classes,packages}/` holds only the `.puml` source; UML image macros are inlined into the tabs partials under `classes/<name>.adoc`; rendered diagrams live under `images/uml/{classes,diagrams}/` |
| `output/PlantUML/` | PlantUML `.puml` diagram files |
| `output/BMM-YAML/` | YAML serialisations of BMM schemas |
| `output/BMM-ODIN/` | ODIN `.bmm` serialisations of BMM schemas (the `specifications-ITS-BMM` schema format) |
| `output/BMM-JSON-development-types/` | Per-type split JSON grouped by component (`AM`, `RM`, `BASE`, `LANG`, `TERM`), plus generation-suffixed dirs for same-id variants (`AM2`, `LANG-bmm3`) |
| `tests/` | Unit and integration tests **and** tool config: `phpunit.xml`, `phpstan.neon`, `phpcs.xml`, `rector.php`, optional `phpstan-baseline.neon` |
| `docs/` | Project documentation. **Place new docs here**, not at the repo root (except README, CONTRIBUTING, CODE_OF_CONDUCT, SECURITY). Index: [docs/README.md](docs/README.md) |
| `.claude/` | Claude Code project instructions (`CLAUDE.md`) |
| `.cursor/rules/` | Cursor rules (`project-context.mdc`, `commit-messages.mdc`, PHP and testing rules) |
| `.github/` | CI workflow, release workflow (GitHub Release + Docker image to GHCR), Dependabot, issue and PR templates, Copilot instructions |
| `.docker/` | Multistage Dockerfile (PHP 8.5-cli Alpine + Alpine `plantuml` package, which pulls in OpenJDK, Graphviz, and DejaVu fonts): `production` target (CI/release, no xdebug) and `development` target (xdebug, Composer, git). Docker Compose targets `development` |
| `README.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md` | Root-level process and team docs |

Coding standards and quality checks are defined by the config files in `tests/` and the Composer scripts in `composer.json`.

> **Note:** this is a CLI project, **not** a Composer library. The deliverable is a Docker image on **GitHub Container Registry** (`ghcr.io/openehr/bmm-publisher`), not a Packagist package.

## CLI commands

| Command | Aliases | Description |
|---------|---------|-------------|
| `asciidoc` | `adoc` | Convert BMM JSON schemas to AsciiDoc tables. Runs the whole pipeline in one invocation: the writer emits `.puml` source plus the tabs partial with the UML image macro inlined, the in-image `plantuml` CLI renders `.svg`, and `EmbedSvg` sanitises and publishes each SVG under `images/uml/classes/` or `images/uml/diagrams/` |
| `legacy-adoc` | | Generate the legacy `docs/UML/classes` layout: flat `org.openehr.<schema>.<class>.adoc` class-definition tables only (no diagrams, effective views, or YAML). `-o <dir>` sets the output directory |
| `plantuml` | `uml`, `puml` | Convert BMM JSON schemas to PlantUML diagrams (standalone tree under `output/PlantUML/`) |
| `embed-svg` | | Re-run only the SVG sanitise and publish step on existing `.svg` files (debugging, re-rendering a few diagrams) |
| `yaml` | | Convert BMM JSON schemas to YAML |
| `split-json` | | Split the latest BMM JSON of each component into per-type files |
| `odin` | | Convert BMM JSON schemas to ODIN `.bmm` schema files, one file per input |

Commands take schema id(s) (without the `.bmm.json` extension) or `.bmm.json` path(s), or `all` to process every schema in `resources/`. `asciidoc`, `legacy-adoc`, and `plantuml` also accept repeatable **`-d <schema>`** dependencies, which are loaded for cross-reference resolution but **not** exported.

## Architecture

```text
bin/bmm-publisher  (Symfony Console Application)
  └── Command  →  BmmSchemaCollection  →  Writer  →  Formatter
```

- **BmmSchemaCollection** loads `.bmm.json` into `BmmSchema` objects (via `cadasto/openehr-bmm`) and provides cross-schema lookups.
- **Writers** are standalone callable classes (`__invoke()`) that each receive the collection: `Asciidoc`, `EmbedSvg`, `PlantUml`, `BmmYaml`, `BmmJsonSplit`, `BmmOdin`, `LegacyClassDefinitions`. **Formatters** turn model objects into output strings.
- **Gotcha, schema-id collisions**: the collection keys schemas by `getSchemaId()`, so same-id inputs (for example `openehr_lang_1.1.0` and the `…-bmm3` overlay) overwrite each other. Writers that must emit both process each input separately and disambiguate the output.
- **Gotcha, package-diagram names**: `Asciidoc` names a package diagram after the package's last name segment, so packages that share one (AM `aom2.archetype` and `aom2.persistence.archetype`) must be disambiguated or the later file overwrites the earlier. `Asciidoc::resolvePackageDiagramNames()` does this; `WriterTest` asserts one diagram per package.

[docs/architecture.md](docs/architecture.md) has the full pipeline diagram, the SVG and Antora details, and the complete list of key patterns.

## Standards (for contributors and agents)

- **Style**: PSR-12 (PHPCS; config in `tests/phpcs.xml`).
- **Static analysis**: PHPStan level 8 (`tests/phpstan.neon`).
- **Tests**: PHPUnit 13 (`tests/phpunit.xml`). Use `declare(strict_types=1);` and type hints. Every fixed bug or non-trivial feature gets a test.
- **Refactoring**: Rector (config in `tests/rector.php`). Run **`composer rector`** inside the dev container (`make sh` or `docker compose … run --rm app composer rector`). Rector is not part of CI.
- **Branching**: `main` is releasable. Use feature or fix branches, and run **`make ci`** (or the equivalent Docker `composer ci`) before opening a PR.
- **Commit messages**: conventional style, **`type: imperative subject`**, imperative mood (`add`, `fix`, not `added` or `Enhances…`), subject under ~72 characters, optional body after a blank line. Types: `feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `ci`. One-line hint when drafting: `conventional commit, <72 chars, feat/refactor/fix`.
  - **Good:** `refactor: map generic params in BmmGenericType::fromArray`
  - **Bad:** `Enhance BmmGenericType::fromArray method to process generic parameters, ensuring proper conversion…`
- **CHANGELOG entries**: keep each bullet to **1 sentence** (about 25 words). Lead with the user-visible change in **bold**; a brief parenthetical or em-dash clause is fine for the *why*. Leave code paths, class renames, and rationale to commit messages and PR descriptions. Compare the 0.4.0 to 0.7.0 entries for the target density.

Keep PHPUnit, PHPStan, PHPCS, and Rector config under `tests/` so the project root stays minimal.

**PHP version**: PHP 8.5+ everywhere. `composer.json` requires `^8.5`, and the Docker dev and prod images and the CI and release workflows all run PHP 8.5.

## Documentation

Index: [docs/README.md](docs/README.md). Read these before changing behaviour:

- [docs/ai-workflow.md](docs/ai-workflow.md): agent working process (container-only tooling, editing guardrails, verification checklist, entrypoint map). Each agent tool (Claude Code, Cursor, GitHub Copilot) has a minimal entrypoint file that defers to this file and to that document.
- [docs/architecture.md](docs/architecture.md): pipeline, writers, and key patterns
- [docs/development.md](docs/development.md): Composer scripts, tooling, and Docker images
- [docs/releases.md](docs/releases.md): version bump (CHANGELOG + `bin/bmm-publisher`), tagging, and Docker image publishing

## Escalation and support

- **Triage**: open or assign a GitHub issue with the `triage` label. For release blockers, follow CONTRIBUTING.md.
- **Usage or design questions**: GitHub Discussion or Issue. **Bugs and features**: use the matching issue form.
- **Security**: do **not** open a public issue. Follow `SECURITY.md`.
