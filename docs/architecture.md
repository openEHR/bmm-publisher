# Architecture

```text
bin/bmm-publisher  (Symfony Console Application)
  └── Command  →  BmmSchemaCollection  →  Writer

BmmSchemaCollection: loads .bmm.json → BmmSchema objects (via cadasto/openehr-bmm)
                     provides cross-schema class/package lookups

Writers (callable classes, receive BmmSchemaCollection, delegate to Formatters):
  ├── Asciidoc      → AsciidocDefinition, AsciidocEffective, AsciidocTab (UML image macro inlined),
  │                   AsciidocBmmJson, Formatter\PlantUml (raw .puml only)
  ├── EmbedSvg      → SvgSanitiser  (post-render: validate, strip MD5, publish .svg under images/uml/)
  ├── PlantUml      → Formatter\PlantUml
  ├── BmmYaml       (uses Symfony Yaml component)
  └── BmmJsonSplit  (per-type JSON with openEHR spec URLs)

The `asciidoc` command is a self-contained pipeline (one PHP run, one container start):
  1. The `Asciidoc` writer emits raw `.puml` files under `output/Adoc/<schema>/plantUML/{classes,packages}/`.
     The tabs partial under `classes/<name>.adoc` already inlines the UML image macro
     (`image::ROOT:uml/classes/<name>.svg[]`); the `ROOT:` qualifier ensures Antora
     resolves the asset in the ROOT module's `images/` tree regardless of which module
     includes the partial.
  2. `AsciidocCommand` shells out via Symfony Process to the bundled `plantuml -tsvg -nometadata` CLI;
     one batch invocation per call (single warm JVM); SVGs land next to each `.puml`.
  3. The `EmbedSvg` writer validates each `.svg` (fails on `Syntax Error?`), strips MD5 stamps,
     publishes the cleaned SVG to `output/Adoc/<schema>/images/uml/classes/<name>.svg` (per-class)
     or `images/uml/diagrams/<name>.svg` (per-package), and unlinks the original.

End state on disk: `<name>.puml` (committed source under `plantUML/{classes,packages}/`) +
tabs partial with inline image macro under `classes/<name>.adoc` +
`images/uml/{classes,diagrams}/<name>.svg` (committed rendered diagram, ready for Antora
to relocate to `<module>/images/uml/{classes,diagrams}/`).
```

## Key patterns

- **BmmSchemaCollection** loads BMM JSON files, provides iteration over schemas, and cross-schema lookups (`getClass()`, `getClassPackageQName()`).
- **Writers** are standalone callable classes (`__invoke()`), each receiving `BmmSchemaCollection` via constructor.
- **Filesystem** helper provides `assureDir()` and `writeFile()` used by all writers.
- **Formatters** are readonly classes that transform BMM model objects into output strings.
- **Logging**: PSR-3 through Symfony `ConsoleLogger`. Commands create it and inject it into `BmmSchemaCollection`; writers reach it as `$schemas->logger`. Progress is logged at `notice` level (shown with `-v`) and detail at `info` (`-vv`).
- **`ResourcesDir`** resolves the input schemas path (hardcoded to `{cwd}/resources`).
- **`OutputDir`** resolves the output path: override via `BMM_OUTPUT_DIR` env var (for Docker), defaults to `{cwd}/output`.
- **Schema-id collisions**: `BmmSchemaCollection` keys schemas by `getName()`, which equals `getSchemaId()` (`rm_publisher_schemaName_rmRelease`). Two input files with the same id overwrite each other in one collection, and most writers key their output by schema id. One example is `openehr_lang_1.1.0.bmm.json` and the BMM v3 overlay `openehr_lang_1.1.0-bmm3.bmm.json`, which both resolve to `openehr_lang_1.1.0`. Writers that must emit both process each input in its own collection and disambiguate the output: `Asciidoc` namespaces filenames (`bmm3.`, `aom14.`, `aom2.`), `BmmYaml` names files by input filename, and `BmmJsonSplit` uses a suffixed component directory (`LANG-bmm3/`).
- **Package-diagram name clashes**: `Asciidoc` names a package diagram after the package's last name segment (`RM-composition`, `AM-aom2.archetype`). Several packages of one schema can share that segment (AM `aom2.archetype` and `aom2.persistence.archetype`, BASE `bmm.core` and `expression.core`). The package nearest the component root keeps the short name, ties go to the first in traversal order, and the others are named by their full path below the component (`AM-aom2.persistence.archetype`).
