# Installation & usage (Docker)

The published production image (`ghcr.io/openehr/bmm-publisher`) ships with all openEHR BMM schemas, the `plantuml` CLI (with OpenJDK and Graphviz), and runs `bmm-publisher` as its entrypoint — pass the command and arguments directly. No PHP, Composer, or local checkout is required.

For the command list and aliases, see the **Commands** table in [README.md](../README.md). For local development against the source, see [development.md](development.md).

## Running the image

```bash
# Bundled schemas, output to a local directory
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

The `asciidoc` command is self-contained: it writes the AsciiDoc tables (with the UML image macro already inlined under the UML tab), runs PlantUML to render every class diagram to SVG, and publishes those SVGs under `output/Adoc/<schema>/images/uml/{classes,diagrams}/` — all in a single invocation.

## Input / output

- **Input**: BMM schemas in `resources/` (`.bmm.json` files, shipped with the image)
- **Output**: generated artefacts in `output/` — mount a volume to retrieve them

Override paths via environment variables:

```bash
docker run --rm \
  -e BMM_OUTPUT_DIR=/data/out \
  -v ./results:/data/out \
  ghcr.io/openehr/bmm-publisher asciidoc all
```

## Running as the host user

By default the image runs as the bundled `app` user (uid 1000). To match the host user — so generated files in a bind-mounted `output/` are owned by your host uid — pass `--user`:

```bash
docker run --rm \
  --user $(id -u):$(id -g) \
  -v ./my-output:/app/output \
  ghcr.io/openehr/bmm-publisher asciidoc all
```

The image supports arbitrary uids: any non-root user retains gid 0, and `/app/output` is group-writable, so writes succeed without rebuilding the image. Bundled `resources/*.bmm.json` ship as 0644, so files copied out with `docker cp` are world-readable on the host.

## Tagged images

Tagged images follow SemVer: `ghcr.io/openehr/bmm-publisher:1.0.0`, `:1.0`, `:1`. See [releases.md](releases.md) for the release/publishing process.
