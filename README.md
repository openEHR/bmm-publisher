# bmm-publisher

PHP CLI tool that reads openEHR BMM schemas and publishes class definitions as AsciiDoc, PlantUML, and YAML for the [openEHR specifications website](https://specifications.openehr.org/).

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
