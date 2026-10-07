# Development reference

## PHP tools (Docker)

Run `composer`, `php` and `vendor/bin/*` inside the dev container, not on the host. From the repository root:

| Command | Purpose |
|---------|---------|
| `make install` | `composer install` in the container |
| `make ci` | Full CI: lint, PHPCS, PHPStan, PHPUnit |
| `make sh` | Interactive shell in the container |
| `docker compose -f .docker/docker-compose.yml run --rm app composer <script>` | Run any Composer script |

To run a single test class:

```bash
docker compose -f .docker/docker-compose.yml run --rm app composer test -- --filter BmmSchemaCollectionTest
```

## CLI commands

Run these inside the container (`make sh`) or through Docker:

```bash
# Export RM 1.2.0; load BASE as a cross-reference dependency only (-d), not exported
./bin/bmm-publisher asciidoc openehr_rm_1.2.0 -d openehr_base_1.3.0
./bin/bmm-publisher legacy-adoc openehr_rm_1.2.0 -d openehr_base_1.3.0
./bin/bmm-publisher plantuml all
./bin/bmm-publisher yaml openehr_base_1.3.0
./bin/bmm-publisher split-json
```

Use `-v` for progress output and `-vv` for detailed file-write logging. `asciidoc`, `legacy-adoc` and `plantuml` accept repeatable `-d <schema>` dependencies, which are loaded for cross-references but not exported. Inputs can be schema ids or `.bmm.json` paths.

## Composer scripts

Run these through `make …` or `docker compose … app composer …` as shown above.

| Script | Description |
|--------|-------------|
| `composer test` | Run PHPUnit |
| `composer test:dox` | PHPUnit with testdox output |
| `composer test:coverage` | PHPUnit with an HTML coverage report in `var/` |
| `composer check:lint` | parallel-lint (syntax) |
| `composer check:cs` | PHPCS (PSR-12) |
| `composer check:phpstan` | PHPStan (level 8) |
| `composer check:phpstan-baseline` | Generate the PHPStan baseline |
| `composer rector` | Run Rector refactoring (applies changes) |
| `composer rector:dry-run` | Run Rector in dry-run mode (no changes) |
| `composer ci` | Run lint, CS, PHPStan and tests (what CI runs) |

## Standards and tooling

- **Coding style**: PSR-12, enforced by PHPCS (config in `tests/phpcs.xml`).
- **Static analysis**: PHPStan level 8 (config in `tests/phpstan.neon`).
- **Tests**: PHPUnit 13 (config in `tests/phpunit.xml`).
- **Refactoring**: Rector (config in `tests/rector.php`). Run it locally; CI does not run it.

## Docker images

The Dockerfile (`.docker/Dockerfile`) is multistage:

| Target | Purpose | Includes |
|--------|---------|----------|
| `base` (shared) | Foundation for both targets | PHP 8.5-cli Alpine and the Alpine `plantuml` package, which pulls in OpenJDK, Graphviz and DejaVu fonts for the `asciidoc` pipeline |
| `development` | Local development and CI Composer scripts | base + xdebug, Composer, git, `php.ini-development` |
| `production` | Release image pushed to GHCR | base + bundled BMM resources, `php.ini-production`, `ENTRYPOINT ["php", "bin/bmm-publisher"]`; no xdebug, no Composer |

```bash
make build          # Build the development image (docker-compose default)
make build-prod     # Build the production image locally
```

The directory layout (`resources/`, `output/`, namespaces) is described in [AGENTS.md](../AGENTS.md), section "Layout and ownership".
