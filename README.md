# bmm-publisher

[![CI](https://github.com/openEHR/bmm-publisher/actions/workflows/ci.yml/badge.svg)](https://github.com/openEHR/bmm-publisher/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/github/license/openEHR/bmm-publisher)](LICENSE)
[![Docker](https://img.shields.io/badge/ghcr.io-openehr%2Fbmm--publisher-2496ED?logo=docker&logoColor=white)](https://ghcr.io/openehr/bmm-publisher)

bmm-publisher is a command-line tool that reads [openEHR](https://openehr.org/) BMM (Basic Meta-Model) schemas and generates class documentation for the [openEHR specifications website](https://specifications.openehr.org/).

The [BMM](https://specifications.openehr.org/releases/LANG/latest/bmm.html) is a formal model that defines the type systems behind the openEHR Reference Model, Archetype Model and related components. Each component's classes, properties, functions and type relationships are stored as [P_BMM](https://specifications.openehr.org/releases/LANG/latest/bmm_persistence.html) JSON schema files. bmm-publisher turns those files into:

- **AsciiDoc** tables: class definitions, effective (flattened) views and cross-referenced type links. Class diagrams are rendered to SVG and referenced from the tabs partial with `image::ROOT:uml/classes/<name>.svg[]`, so the site build needs neither Kroki nor asciidoctor-diagram. The SVGs (per class and per package) are committed under `output/Adoc/<schema>/images/uml/{classes,diagrams}/`.
- **PlantUML** sources: `.puml` files for the same diagrams, kept next to the generated partials as the source of truth.
- **YAML**: a machine-readable serialisation of each schema.
- **ODIN**: `.bmm` files in the native openEHR BMM schema format.
- **Per-type JSON**: one file per class, with links back to the relevant specification page.

## Quick start (Docker)

The production image contains all openEHR BMM schemas and the `plantuml` CLI, and it starts `bmm-publisher` as its entrypoint, so you pass only the command. The `asciidoc` command does everything in one run: it writes the tables, renders every class diagram to SVG, and publishes the SVGs under `output/Adoc/<schema>/images/uml/{classes,diagrams}/`.

```bash
docker run --rm -v ./my-output:/app/output ghcr.io/openehr/bmm-publisher asciidoc all
```

[docs/install.md](docs/install.md) covers the other ways to run the image: your own schemas, a single schema, the `BMM_OUTPUT_DIR` override, host-user mapping, and `-v` and `-vv` logging.

## Commands

| Command | Aliases | Description |
|---------|---------|-------------|
| `asciidoc` | `adoc` | Convert BMM JSON schemas to AsciiDoc tables, with class and package diagrams pre-rendered as standalone SVGs under `images/uml/{classes,diagrams}/` and referenced from the tabs partial with `image::ROOT:uml/classes/<name>.svg[]` |
| `legacy-adoc` | | Generate the legacy `docs/UML/classes` layout: flat per-class definition tables only (`-o <dir>` sets the output directory) |
| `plantuml` | `uml`, `puml` | Generate the standalone PlantUML source tree (`output/PlantUML/<schema>/...`), for when you want only the `.puml` files |
| `embed-svg` | | Re-run only the SVG sanitise and publish step on existing `.svg` files, for debugging or re-rendering a few diagrams |
| `yaml` | | Convert BMM JSON schemas to YAML |
| `split-json` | | Split the latest BMM JSON of each component into per-type files |
| `odin` | | Convert BMM JSON schemas to ODIN `.bmm` schema files |

Pass schema names without the `.bmm.json` extension (paths to `.bmm.json` files also work), or `all` to process every schema in the input directory. `asciidoc`, `legacy-adoc` and `plantuml` accept repeatable `-d <schema>` options for dependencies, which are loaded for cross-references but not exported.

## Input and output

- **Input**: BMM schemas in `resources/` (`.bmm.json` files, shipped with the image).
- **Output**: generated artefacts in `output/`. Mount a volume to retrieve them.

[docs/install.md](docs/install.md) explains the `BMM_OUTPUT_DIR` override, mounting your own schemas, and running as the host user (uid and gid).

## Extending bmm-publisher

BMM describes the whole openEHR type system: classes, inheritance, properties, generics, functions and constraints. That makes it a source for more than documentation. If you need an output this tool does not produce, fork the repository and add a writer. A writer is a single callable class that receives the loaded schemas and writes files: `BmmSchemaCollection` loads and indexes the schemas, and your writer iterates over them.

Possible outputs:

- code skeletons (class stubs with typed properties and method signatures) in PHP, Java, C#, Python or TypeScript
- JSON Schema or OpenAPI schemas for REST APIs that exchange openEHR data
- GraphQL type definitions backed by the Reference Model
- database DDL that maps classes to tables or collections
- other documentation formats, such as Markdown, HTML, Docusaurus pages or Confluence markup
- diff reports between two BMM versions, listing added, removed and changed classes and properties
- test fixtures built from the type constraints

## Development

Development needs Docker. The development image includes xdebug and Composer.

```bash
make install        # Install PHP dependencies
make ci             # Run full CI checks (lint, PHPCS, PHPStan, tests)
make sh             # Interactive shell in the dev container
make build-prod     # Build the production image locally
```

Inside the dev container:

```bash
./bin/bmm-publisher asciidoc openehr_rm_1.2.0 openehr_base_1.3.0
composer test
composer check:phpstan
```

[docs/development.md](docs/development.md) lists every Composer script and the tooling.

## AI agents

[AGENTS.md](AGENTS.md) describes the project structure, standards and architecture for AI agents.

## License

[Apache-2.0](LICENSE)
