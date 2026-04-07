# Changelog

All notable changes to this project should be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-04-06

First release: a small command-line tool that turns openEHR BMM JSON into AsciiDoc, PlantUML, YAML, and extra JSON files for the specs site.

### Added

- CLI with **asciidoc**, **plantuml**, **yaml**, and **split-json**; point at schema names or **all** to process everything in the input folder.
- Reads **BMM JSON** from `resources/`, writes generated files under `output/` (output path overridable via **`BMM_OUTPUT_DIR`**).
- Docker image on GitHub Container Registry, plus Makefile and CI for builds, checks, and releases.
- Basic automated tests and static analysis for the PHP codebase.
- Contributor docs and templates.

Input is BMM JSON only (not XMI/UML exchange files).

[Unreleased]: https://github.com/openehr/bmm-publisher/compare/0.1.0...HEAD
[0.1.0]: https://github.com/openehr/bmm-publisher/releases/tag/0.1.0
