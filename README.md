# bmm-publisher

PHP CLI tool that reads openEHR BMM schemas and publishes class definitions as AsciiDoc, PlantUML, and YAML for the [openEHR specifications website](https://specifications.openehr.org/).

## Requirements

- Docker (for development and running)
- Alternatively: PHP 8.4+ with Composer

## Quick start

```bash
make install        # Install PHP dependencies
make ci             # Run full CI checks
```

## Usage

```bash
# Inside the container (make sh) or via Docker:
./bin/bmm-publisher asciidoc openehr_rm_1.2.0 openehr_base_1.3.0
./bin/bmm-publisher plantuml all
./bin/bmm-publisher yaml openehr_base_1.3.0
./bin/bmm-publisher split-json
```

### Commands

| Command | Aliases | Description |
|---------|---------|-------------|
| `asciidoc` | `adoc` | Convert BMM JSON schemas to AsciiDoc tables |
| `plantuml` | `uml`, `puml` | Convert BMM JSON schemas to PlantUML diagrams |
| `yaml` | | Convert BMM JSON schemas to YAML format |
| `split-json` | | Split latest BMM JSON of each component into per-type files |

Pass schema name(s) without `.bmm.json` extension, or `all` to process every schema in `resources/`.

Use `-v` for progress output, `-vv` for detailed logging.

### Input / Output

- **Input**: BMM schemas in `resources/` (`.bmm.json` files, shipped with the repo)
- **Output**: Generated artefacts in `output/` (gitignored), configurable via `BMM_OUTPUT_DIR` env var

## Development

See [docs/development.md](docs/development.md) for Composer scripts, tooling, and Docker workflow.

See [AGENTS.md](AGENTS.md) for project structure, standards, and architecture.

## License

[Apache-2.0](LICENSE)
