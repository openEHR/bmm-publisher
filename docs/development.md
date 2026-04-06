# Development reference

## PHP tools (Docker)

**`composer`, `php`, and `vendor/bin/*` are intended to run inside the dev container**, not on arbitrary host machines. From the **repository root**:

| Command | Purpose |
|---------|---------|
| `make install` | `composer install` in the container |
| `make ci` | Full CI: lint, PHPCS, PHPStan, PHPUnit |
| `make sh` | Interactive shell in the container |
| `docker compose -f .docker/docker-compose.yml run --rm app composer <script>` | Run any Composer script (e.g. `test`, `check:phpstan`) |

Example — run a single test class:

```bash
docker compose -f .docker/docker-compose.yml run --rm app composer test -- --filter CollectionTest
```

## Composer scripts

Commands below are run **via** `make …` or `docker compose … app composer …` as above.

| Script | Description |
|--------|-------------|
| `composer test` | Run PHPUnit |
| `composer test:dox` | PHPUnit with testdox output |
| `composer test:coverage` | PHPUnit with HTML coverage report in `var/` |
| `composer check:lint` | parallel-lint (syntax) |
| `composer check:cs` | PHPCS (PSR-12) |
| `composer check:phpstan` | PHPStan (level 8) |
| `composer check:phpstan-baseline` | Generate PHPStan baseline |
| `composer rector` | Run Rector refactoring (applies changes) |
| `composer rector:dry-run` | Run Rector in dry-run (no changes) |
| `composer ci` | Run lint, CS, PHPStan, and tests (what CI runs) |

## Standards and tooling

- **Coding style**: PSR-12 (enforced by PHPCS; config in `tests/phpcs.xml`).
- **Static analysis**: PHPStan level 8 (config in `tests/phpstan.neon`).
- **Tests**: PHPUnit 12 (config in `tests/phpunit.xml`).
- **Refactoring**: Rector (config in `tests/rector.php`; run locally; not in CI by default).
