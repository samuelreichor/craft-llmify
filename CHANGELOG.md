# Release Notes for LLMify

## 0.1.0 - 2026-03-27

### Added
- Add full Craft v4 Support

### Previous Changes from v1 (Craft v5 version)
- Add icon to llmify settings field.
- Add Craft Commerce product support as an optional soft dependency.
- Add `X-Robots-Tag: noindex, nofollow` header to all markdown endpoints.
- Add `Link: rel="canonical"` header to individual markdown pages pointing to the original HTML page.
- Expand default `remove_nodes` to strip non-content elements (`form`, `button`, `input`, `select`, `option`, `svg`, `script`, `nav`, `noscript`).
- Remove empty markdown links left behind after image/node removal.
- Decode HTML entities in generated markdown output.
- Generalize database schema from entry-specific to element-generic (`entryId` → `elementId`, `sectionId` → `groupId`, `entryMeta` → `elementMeta`).
- Generalize all services to work with `ElementInterface` instead of `Entry`.
- Add auto serve markdown mode if the `text/markdown` header is present.
- Add auto serve markdown setting.
- Add blitz support for auto serve markdown mode.
- Add link to generated markdown in entry sidebar.
- Add auto try to generate markdown files before showing a 404 for requests with `text/markdown` and `raw/${uri}.md` routes.
- Add front matter support with hierarchical inheritance (Site → Section → Entry)
- Add Content Block text fields support with dot notation
- Add entry-level override settings for title, description and front matter
- Add link to section settings in entry field UI
- Fix request context issues in `EVENT_AFTER_RENDER_TEMPLATE`. [#1](https://github.com/samuelreichor/craft-llmify/issues/1)
- Remove punctuation mark at the end of the llms.txt description and filter out links without title or url.
