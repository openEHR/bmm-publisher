# Contributing

Thanks for contributing!

## Branching strategy

- `main` is always releasable.
- Create feature/fix branches from `main`:
  - `feat/<short-description>`
  - `fix/<short-description>`
  - `chore/<short-description>`

## Commit messages

Use **conventional style** with a short subject and type prefix (e.g. `feat: add X`, `fix: handle Y`, `chore: update Z`, `docs: …`, `refactor: …`). Keep the subject line under ~72 characters, imperative mood. Add an optional body after a blank line for more detail.

## Pull request requirements

Before opening a PR:

1. Rebase on latest `main`.
2. Run all checks locally:

```bash
composer install
composer ci
```

Or via Docker (from repo root; Docker files in `.docker/`):

```bash
docker compose -f .docker/docker-compose.yml run --rm app composer install
docker compose -f .docker/docker-compose.yml run --rm app composer ci
```

Or use the Makefile: `make install` then `make ci`.

3. Update docs (in `docs/` where applicable) and tests for behavior changes.

## Required checks

PRs must pass:

- `composer check:cs`
- `composer check:phpstan`
- `composer test`

## Code style and quality

- Follow PSR-12 coding style.
- Use strict typing (`declare(strict_types=1);`).
- Keep public API changes intentional and documented.
- Add or update tests for each behavior change.

This project commits `composer.lock` to ensure reproducible builds across CI, Docker, and development environments.

## Security reporting

Do **not** disclose vulnerabilities publicly.

Report them privately to the team (e.g. via GitHub's private vulnerability reporting as described in SECURITY.md). Include reproduction steps and impact assessment.
