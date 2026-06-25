# Changelog

All notable changes to this project should be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- **AsciiDoc class tables no longer escape a lone asterisk to `&#42;`** — `formatText()` previously replaced fixed substrings (`.*`, `'*'`, `)*`, `]*`) with the `&#42;` HTML entity, escaping single regex/multiplicity asterisks (e.g. `0..*`, `` `[a-z]*` ``) that render perfectly well as literal `*` and diverge from the hand-authored spec style. Escaping is now applied per line and only when two or more asterisks remain after excluding a leading list marker — so a lone asterisk stays literal while genuine multi-asterisk regexes (e.g. `(0|[1-9][0-9]*)...*`) are still escaped to prevent stray inline bold. Affected committed `output/Adoc` and `output/legacy-adoc` snapshots regenerated.
- **Normalised pre-escaped `&#42;` HTML entities in the bundled BMM source data** — the `multiply` operator alias, the `Multiplicity_unbounded_marker` constant, and the `Regex_any_pattern` constant stored a literal asterisk as `&#42;` in `resources/openehr_base_1.2.0`, `openehr_base_1.3.0`, `openehr_rm_1.1.0`, and `openehr_rm_1.2.0`. The model now carries the plain `*` character (output-format escaping is the publisher's job), removing a cross-version inconsistency where the same constant rendered as `*` in some schemas and `&#42;` in others. All derived snapshots regenerated.

## [0.9.0] - 2026-06-03

### Added

- **`odin` command** — serialises BMM JSON schemas to ODIN `.bmm` files under `output/BMM-ODIN/`, one file per input (also exposed as `make odin`). Covers packages, classes, properties, generics, enumerations, cardinalities, functions, and invariants.

### Changed

- **`cadasto/openehr-bmm` upgraded to `^0.3`** — `toArray()` now flattens nested generic types, so the `BmmOdin` writer no longer needs its JSON round-trip workaround.

### Fixed

- **YAML output for AM schemas no longer drops nested generic type parameters** — `generic_parameters` members such as `TUPLE2<String, String>` were serialised as `null`; they now render as proper `!P_BMM_GENERIC_TYPE` nodes (fixed by the `cadasto/openehr-bmm` 0.3 upgrade).

## [0.8.0] - 2026-06-02

### Added

- **`legacy-adoc` command** — generates the legacy `docs/UML/classes` layout (flat `org.openehr.<schema>.<class>.adoc` class-definition tables) from BMM JSON, with `-d` dependencies and an `-o` output dir; also exposed as `make legacy-adoc`.
- **BMM v3 publishing** — the `openehr_lang_1.1.0-bmm3` overlay now publishes alongside legacy LANG: AsciiDoc namespaces its filenames with a `bmm3.` prefix (joining the existing `aom14.`/`aom2.` AM prefixes), YAML is named after the input file, and split-json emits a `LANG-bmm3/` component directory.
- **`-d` dependency option** on `asciidoc`, `plantuml`, and `legacy-adoc` — load schemas for cross-reference resolution without exporting them; these commands also accept `.bmm.json` paths.

### Changed

- **`asciidoc` writes per-schema output directories and prunes diagrams by namespace** — schemas sharing an output directory no longer wipe each other's committed `plantUML`/`images` files; the old single-tree legacy format was removed in favour of the dedicated `legacy-adoc` command.
- **AI-assistance docs restructured** into gradual-disclosure tiers — `AGENTS.md` stays canonical, deep detail split into `docs/{architecture,ai-workflow,install,development,releases}.md`, and the `.claude`/`.cursor`/`.junie`/Copilot entrypoints slimmed to pointers.

### Fixed

- **`yaml` and `split-json` no longer drop inputs that share a schema id** (e.g. `openehr_lang_1.1.0` and its `-bmm3` overlay) — each input is processed independently and written to a distinct file/directory instead of silently overwriting the other.

## [0.7.0] - 2026-05-01

### Changed

- **Minimum PHP version** bumped to 8.5 — `composer.json` now requires `^8.5` and the CI matrix runs only on 8.5.
- **Production Docker image** supports arbitrary `--user UID:GID` (group-writable `/app/output` + `HOME=/tmp`); see README.

### Fixed

- **UML image macro** in the tabs partial now qualified as `image::ROOT:uml/classes/<name>.svg[]` so Antora resolves it under the ROOT module when the partial is included from another module.
- **`Helper\Filesystem::assureDir()`** no longer rejects pre-existing unwritable dirs — fixes spurious `Directory ... is not writable.` errors with nested bind mounts under `output/Adoc/<schema>/`.

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

[Unreleased]: https://github.com/openehr/bmm-publisher/compare/0.9.0...HEAD
[0.9.0]: https://github.com/openehr/bmm-publisher/compare/0.8.0...0.9.0
[0.8.0]: https://github.com/openehr/bmm-publisher/compare/0.7.0...0.8.0
[0.7.0]: https://github.com/openehr/bmm-publisher/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/openehr/bmm-publisher/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/openehr/bmm-publisher/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/openehr/bmm-publisher/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/openehr/bmm-publisher/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/openehr/bmm-publisher/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/openehr/bmm-publisher/releases/tag/0.1.0
