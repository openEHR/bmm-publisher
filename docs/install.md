# Install and usage (Docker)

The published production image (`ghcr.io/openehr/bmm-publisher`) contains all openEHR BMM schemas and the `plantuml` CLI (with OpenJDK and Graphviz), and it starts `bmm-publisher` as its entrypoint. Pass the command and its arguments directly. You need no PHP, Composer or local checkout.

The **Commands** table in [README.md](../README.md) lists the commands and aliases. [development.md](development.md) covers working against the source.

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

Use `-v` for progress output and `-vv` for detailed file-write logging:

```bash
docker run --rm -v ./my-output:/app/output ghcr.io/openehr/bmm-publisher asciidoc -v all
```

The `asciidoc` command runs the whole pipeline in one invocation. It writes the AsciiDoc tables (with the UML image macro already inlined under the UML tab), runs PlantUML to render every class diagram to SVG, and publishes the SVGs under `output/Adoc/<schema>/images/uml/{classes,diagrams}/`.

## Input and output

- **Input**: BMM schemas in `resources/` (`.bmm.json` files, shipped with the image). To use your own, mount a directory at `/app/resources`.
- **Output**: generated artefacts in `output/`. Mount a volume to retrieve them.

Set `BMM_OUTPUT_DIR` to write the output somewhere else:

```bash
docker run --rm \
  -e BMM_OUTPUT_DIR=/data/out \
  -v ./results:/data/out \
  ghcr.io/openehr/bmm-publisher asciidoc all
```

## Running as the host user

By default the image runs as the bundled `app` user (uid 1000). To make the generated files in a bind-mounted `output/` belong to your host uid, pass `--user`:

```bash
docker run --rm \
  --user $(id -u):$(id -g) \
  -v ./my-output:/app/output \
  ghcr.io/openehr/bmm-publisher asciidoc all
```

The image supports arbitrary uids: any non-root user retains gid 0, and `/app/output` is group-writable, so writes succeed without rebuilding the image. The bundled `resources/*.bmm.json` files ship as 0644, so files copied out with `docker cp` are world-readable on the host.

## Tagged images

Tagged images follow SemVer: `ghcr.io/openehr/bmm-publisher:1.0.0`, `:1.0` and `:1`. [releases.md](releases.md) describes the release and publishing process.
