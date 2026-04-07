# Releases

Version tags are **SemVer only** (no `v` prefix): e.g. `1.0.0`, `2.1.3`. The release workflow runs only on tags matching that pattern and on `main`.

1. Ensure `main` is green (all CI checks pass).
2. Tag and push:

   ```bash
   git tag 1.0.0
   git push origin 1.0.0
   ```

3. The release workflow:
   - Verifies the tag is on `main`.
   - Runs `composer ci` to validate.
   - Creates a GitHub Release with auto-generated release notes.
   - Builds the `production` Docker image and pushes it to **GitHub Container Registry** (`ghcr.io/openehr/bmm-publisher`).

## Docker image

Tagged images follow SemVer: `ghcr.io/openehr/bmm-publisher:1.0.0`, `:1.0`, `:1`.

The production image runs `bmm-publisher` as its entrypoint — pass commands directly:

```bash
# Generate AsciiDoc for all bundled schemas, output to local directory
docker run --rm -v ./output:/app/output ghcr.io/openehr/bmm-publisher:1.0.0 asciidoc all

# Use your own BMM schemas
docker run --rm \
  -v ./my-schemas:/app/resources \
  -v ./output:/app/output \
  ghcr.io/openehr/bmm-publisher:1.0.0 yaml all
```
