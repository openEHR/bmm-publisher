# Changelog

All notable changes to this project should be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Production Docker image** now supports arbitrary `--user UID:GID` invocations: `/app/output` is group-writable and `HOME` points at `/tmp`, so non-root host users can run `docker run --user $(id -u):$(id -g) …` without rebuilding. Documented in README.

### Fixed

- **UML image macro in tabs partial** is now qualified as `image::ROOT:uml/classes/<name>.svg[]` so Antora resolves the asset in the ROOT module's `images/` tree even when the partial is included from a page in another module (e.g. `foundation_types`, `base_types`).
- **`Helper\Filesystem::assureDir()`** no longer fails preflight on pre-existing unwritable directories — fixes spurious `Directory ... is not writable.` errors when integrators bind-mount additional volumes under `output/Adoc/<schema>/`. Real permission problems still surface at write time.

## [0.6.0] - 2026-05-01

### Changed

- **Class diagrams** are now committed as standalone SVGs under `output/Adoc/<schema>/images/uml/{classes,diagrams}/` and referenced via an `image::uml/classes/<name>.svg[]` macro inlined into the UML tab of the per-class tabs partial. Reverts 0.5.0's inline-SVG passthrough, which did not render reliably in downstream Antora pipelines.

### Added

- **`Writer\EmbedSvg`** + **`embed-svg` console subcommand** — replace `Writer\InlineSvg` / `inline-svg`; validate the rendered SVG, strip MD5 stamps via `Writer\Formatter\SvgSanitiser`, and publish it under `images/uml/{classes,diagrams}/`.

### Removed

- **`Writer\InlineSvg`**, **`Writer\Formatter\SvgPassthrough`**, the **`inline-svg`** subcommand, and the intermediate `plantUML/{classes,packages}/<name>.adoc` image-reference partials — superseded by the inlined image macro and standalone-SVG pipeline above.

## [0.5.0] - 2026-04-29

### Changed

- **AsciiDoc partials for class diagrams** (`output/Adoc/<schema>/plantUML/{classes,packages}/<name>.adoc`) now contain class diagrams as inline SVG inside Asciidoctor passthrough blocks, instead of `[plantuml,…,format=svg]` source blocks. Downstream Antora sites no longer need `asciidoctor-kroki` to render these diagrams, removing the runtime rate-limit dependency on the public Kroki service.
- **`asciidoc` console command is now atomic**: in a single invocation it writes `.puml` source, renders SVG via the bundled `plantuml` CLI (one batch, single warm JVM), and embeds the SVG as a passthrough block in the `.adoc` partial. The `.puml` source and final `.adoc` are committed; the intermediate `.svg` is removed at the end of the run.
- **Docker images** (both `production` and `development` via the shared `base` stage) bundle the `plantuml` CLI through Alpine's `plantuml` package, which transitively pulls in OpenJDK 25, Graphviz, and DejaVu fonts. Production image grows from ~150 MB to ~330 MB, but consumers no longer need a separate PlantUML installation.
- **`make adoc`** is now a single `docker compose run` (PHP orchestrates the writer → render → inline pipeline internally; previously three separate container starts).
- **`Writer\Asciidoc`** emits raw `.puml` files instead of `[plantuml,…]` block partials, and recursively pre-cleans `output/Adoc/<schema>/plantUML/` on first encounter of each schema in a writer run, so orphaned files (classes renamed across BMM versions) cannot linger in committed output.

### Added

- **`Writer\InlineSvg`** + **`Writer\Formatter\SvgPassthrough`** — the formatter sanitises rendered SVG (strips `<?xml ?>` PI, `<!DOCTYPE>`, PlantUML MD5 stamp comments; fails on PlantUML's `Syntax Error?` marker) and wraps it in a `++++ … ++++` passthrough block; the writer iterates the schema dirs, applies the formatter, writes `.adoc` partials, and unlinks the consumed `.svg`.
- **`inline-svg` console subcommand** — exposes `Writer\InlineSvg` for surgical re-runs against existing `.svg` files (debugging).
- **CI `verify-output` job** — re-runs `make publish-all` on the `verify-output` runner and fails the build on `git diff -- output/`, making committed-output drift a hard fail.
- **`symfony/process` ^8.0** — used by `AsciidocCommand` to invoke the bundled `plantuml` CLI.

### Removed

- **`Writer\Formatter\AsciidocPlantUml`** — the legacy `[plantuml,…,format=svg]` block wrapper, replaced by the new pipeline.

## [0.4.0] - 2026-04-16

### Changed

- **Package traversal**: `BmmSchemaCollection::forEachPackage()` supports one additional nesting level (4 deep), enabling schemas with deeper package hierarchies.

### Added

- **`openehr_lang_1.0.0` schema**: added `entity` package with `BMM_CLASS`, `BMM_CONTAINER_TYPE`, `BMM_EFFECTIVE_TYPE`, `BMM_MODEL_TYPE`, `BMM_GENERIC_CLASS`, `BMM_GENERIC_TYPE`, `BMM_PARAMETER_TYPE`, `BMM_TUPLE_TYPE`, `BMM_TYPE`, `BMM_SIMPLE_CLASS`, `BMM_SIMPLE_TYPE`, `BMM_UNITARY_TYPE`, `BMM_INDEXED_CONTAINER_TYPE`, `BMM_SIGNATURE`, `BMM_MODULE`, `BMM_ENTITY_METATYPE`, and nested `range_constrained` sub-package (`BMM_VALUE_SET_SPEC`, `BMM_ENUMERATION`, `BMM_ENUMERATION_INTEGER`, `BMM_ENUMERATION_STRING`).
- Re-rendered all output files for the updated schema.

## [0.3.0] - 2026-04-08

### Changed

- **Effective AsciiDoc formatter**: inherited members no longer show an ancestor prefix in the signature column (left). Instead, the documentation column (right) shows `_Inherited from {AncestorType}_` as an italic note with a cross-reference link.
- **README**: expanded introduction — explains what BMM and P_BMM are, links to the specs, describes each output format's role in the specifications site. Added "Extend it for your own use" section with examples of downstream artefacts. Added CI, PHP, license, and Docker badges.

## [0.2.0] - 2026-04-07

### Changed

- **Error handling**: commands catch `\Throwable` (was `\UnhandledMatchError`); error message shown at normal verbosity, full trace with `-v`. `Filesystem::writeFile()` throws on write failure.
- **Formatters**: added `declare(strict_types=1)` to all formatter files; extracted shared `formatParameterDocRows()` to reduce duplication between `AsciidocDefinition` and `AsciidocEffective`.
- **`BmmConstant::value`** cast to string before formatting (was failing on integer constants).

### Added

- `BmmSchemaCollection::forEachPackage()` — shared recursive package traversal, replacing duplicated triple-nested loops in 3 writers.
- Caching in `BmmSchemaCollection::getClass()` and `getClassPackageQName()` for faster cross-schema lookups.
- Path traversal protection: `basename()` sanitisation in `BmmSchemaCollection::load()`.
- Integration and unit tests for all 4 writers and both formatters (`AsciidocDefinition`, `PlantUml`). Test count: 26 → 55, assertions: 73 → 159.

## [0.1.0] - 2026-04-06

First release: a small command-line tool that turns openEHR BMM JSON into AsciiDoc, PlantUML, YAML, and extra JSON files for the specs site.

### Added

- CLI with **asciidoc**, **plantuml**, **yaml**, and **split-json**; point at schema names or **all** to process everything in the input folder.
- Reads **BMM JSON** from `resources/`, writes generated files under `output/` (output path overridable via **`BMM_OUTPUT_DIR`**).
- Docker image on GitHub Container Registry, plus Makefile and CI for builds, checks, and releases.
- Basic automated tests and static analysis for the PHP codebase.
- Contributor docs and templates.

Input is BMM JSON only (not XMI/UML exchange files).

[Unreleased]: https://github.com/openehr/bmm-publisher/compare/0.6.0...HEAD
[0.6.0]: https://github.com/openehr/bmm-publisher/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/openehr/bmm-publisher/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/openehr/bmm-publisher/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/openehr/bmm-publisher/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/openehr/bmm-publisher/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/openehr/bmm-publisher/releases/tag/0.1.0
