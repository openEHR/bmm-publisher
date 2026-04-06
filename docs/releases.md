# Releases

Version tags are **SemVer only** (no `v` prefix): e.g. `1.0.0`, `2.1.3`. The release workflow runs only on tags matching that pattern.

1. Ensure `main` is green (all CI checks pass).
2. Tag and push:

   ```bash
   git tag 1.0.0
   git push origin 1.0.0
   ```

3. The release workflow:
   - Runs `composer ci` to validate.
   - Creates a GitHub Release with auto-generated release notes.
   - Builds a Docker image and pushes it to **GitHub Container Registry** (`ghcr.io/openehr/bmm-publisher`).

## Docker image

Tagged images follow SemVer: `ghcr.io/openehr/bmm-publisher:1.0.0`, `:1.0`, `:1`.

To run locally:

```bash
docker pull ghcr.io/openehr/bmm-publisher:latest
docker run --rm ghcr.io/openehr/bmm-publisher publish:asciidoc all
```
