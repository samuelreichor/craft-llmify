# Release Notes for LLMify

## 1.3.1 - 2026-03-16

### Fixed
- Add icon to llmify settings field.

## 1.3.0 - 2026-03-04

### Added
- Add Craft Commerce product support as an optional soft dependency.
- Add `X-Robots-Tag: noindex, nofollow` header to all markdown endpoints.
- Add `Link: rel="canonical"` header to individual markdown pages pointing to the original HTML page.

### Improved
- Expand default `remove_nodes` to strip non-content elements (`form`, `button`, `input`, `select`, `option`, `svg`, `script`, `nav`, `noscript`).
- Remove empty markdown links left behind after image/node removal.
- Decode HTML entities in generated markdown output.

### Changed
- Generalize database schema from entry-specific to element-generic (`entryId` → `elementId`, `sectionId` → `groupId`, `entryMeta` → `elementMeta`).
- Generalize all services to work with `ElementInterface` instead of `Entry`.

## 1.2.0 - 2026-03-01

- Add auto serve markdown mode if the `text/markdown` header is present.
- Add auto serve markdown setting.
- Add blitz support for auto serve markdown mode.
- Add link to generated markdown in entry sidebar.
- Add auto try to generate markdown files before showing a 404 for requests with `text/markdown` and `raw/${uri}.md` routes. 

## 1.1.0 - 2026-02-01

- Add front matter support with hierarchical inheritance (Site → Section → Entry)
- Add Content Block text fields support with dot notation
- Add entry-level override settings for title, description and front matter
- Add link to section settings in entry field UI

## 1.0.2 - 2025-12-08

- Fix request context issues in `EVENT_AFTER_RENDER_TEMPLATE`. [#1](https://github.com/samuelreichor/craft-llmify/issues/1) 

## 1.0.1 - 2025-08-20

- Remove punctuation mark at the end of the llms.txt description and filter out links without title or url.

## 1.0.0 - 2025-08-20
- Initial release
