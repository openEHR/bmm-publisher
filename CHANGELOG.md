# Changelog

All notable changes to this project should be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/openehr/bmm-publisher/compare/0.3.0...HEAD
[0.3.0]: https://github.com/openehr/bmm-publisher/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/openehr/bmm-publisher/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/openehr/bmm-publisher/releases/tag/0.1.0
