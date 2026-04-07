# bmm-publisher

[![CI](https://github.com/openEHR/bmm-publisher/actions/workflows/ci.yml/badge.svg)](https://github.com/openEHR/bmm-publisher/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/github/license/openEHR/bmm-publisher)](LICENSE)
[![Docker](https://img.shields.io/badge/ghcr.io-openehr%2Fbmm--publisher-2496ED?logo=docker&logoColor=white)](https://ghcr.io/openehr/bmm-publisher)

CLI tool that reads [openEHR](https://openehr.org/) **BMM** (Basic Meta-Model) schemas and generates class documentation for the [openEHR specifications website](https://specifications.openehr.org/).

The [BMM](https://specifications.openehr.org/releases/LANG/latest/bmm.html) is a formal model used to define the type systems behind the openEHR Reference Model, Archetype Model, and related components. Each component's classes, properties, functions, and type relationships are expressed as [P_BMM](https://specifications.openehr.org/releases/LANG/latest/bmm_persistence.html) JSON schema files.

This tool processes those schemas and produces:

- **AsciiDoc** tables — class definitions, effective (flattened) views, and cross-referenced type links, embedded directly into the published specification pages
- **PlantUML** diagrams — class and package diagrams rendered as SVG in the specifications
- **YAML** — machine-readable serialisation of each schema
- **Per-type JSON** — individual class files with links back to the relevant specification page

### Extend it for your own use

Because BMM formally describes the complete openEHR type system — classes, inheritance, properties, generics, functions, constraints — it can serve as the source of truth for many downstream artefacts beyond documentation. If you need something this tool doesn't produce out of the box, fork the repo and add your own writer. Each writer is a single callable class that receives the loaded schemas and writes output. Examples of what you could generate:

- **Code skeletons** — PHP, Java, C#, Python, or TypeScript class stubs with typed properties and method signatures derived from the BMM definitions
- **JSON Schema / OpenAPI** — formal validation schemas for REST APIs that exchange openEHR data structures
- **GraphQL types** — type definitions for GraphQL APIs backed by the Reference Model
- **Database schemas** — DDL for relational or document stores, mapping BMM classes to tables or collections
- **Alternative documentation formats** — Markdown, HTML, Docusaurus pages, Confluence wiki markup, or any other publishing format
- **Diff reports** — compare two BMM versions and produce a changelog of added, removed, or changed classes and properties
- **Conformance test data** — generate test fixtures or validation datasets based on the type constraints

The architecture is intentionally simple: `BmmSchemaCollection` loads and indexes all schemas, your writer iterates them and produces files.

## Quick start (Docker)

The production image ships with all openEHR BMM schemas and runs `bmm-publisher` as its entrypoint — just pass the command and arguments:

```bash
# Using bundled schemas, output to a local directory
docker run --rm -v ./my-output:/app/output ghcr.io/openehr/bmm-publisher asciidoc all

# Single schema
docker run --rm -v ./my-output:/app/output ghcr.io/openehr/bmm-publisher plantuml openehr_rm_1.2.0

# With your own BMM schemas
docker run --rm \
  -v ./my-schemas:/app/resources \
  -v ./my-output:/app/output \
  ghcr.io/openehr/bmm-publisher yaml all

# List available commands
docker run --rm ghcr.io/openehr/bmm-publisher list
```

Use `-v` for progress output, `-vv` for detailed file-write logging:

```bash
docker run --rm -v ./my-output:/app/output ghcr.io/openehr/bmm-publisher asciidoc -v all
```

## Commands

| Command | Aliases | Description |
|---------|---------|-------------|
| `asciidoc` | `adoc` | Convert BMM JSON schemas to AsciiDoc tables |
| `plantuml` | `uml`, `puml` | Convert BMM JSON schemas to PlantUML diagrams |
| `yaml` | | Convert BMM JSON schemas to YAML format |
| `split-json` | | Split latest BMM JSON of each component into per-type files |

Pass schema name(s) without `.bmm.json` extension, or `all` to process every schema in the input directory.

## Input / Output

- **Input**: BMM schemas in `resources/` (`.bmm.json` files, shipped with the image)
- **Output**: Generated artefacts in `output/` — mount a volume to retrieve them

Override paths via environment variables:

```bash
docker run --rm \
  -e BMM_OUTPUT_DIR=/data/out \
  -v ./results:/data/out \
  ghcr.io/openehr/bmm-publisher asciidoc all
```

## Development

Requires Docker. The development image includes xdebug and Composer.

```bash
make install        # Install PHP dependencies
make ci             # Run full CI checks (lint, PHPCS, PHPStan, tests)
make sh             # Interactive shell in dev container
make build-prod     # Build production image locally
```

Inside the dev container:

```bash
./bin/bmm-publisher asciidoc openehr_rm_1.2.0 openehr_base_1.3.0
composer test
composer check:phpstan
```

See [docs/development.md](docs/development.md) for full Composer scripts and tooling reference.

See [AGENTS.md](AGENTS.md) for project structure, standards, and architecture.

## License

[Apache-2.0](LICENSE)
