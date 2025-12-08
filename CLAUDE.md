# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LLMify is a Craft CMS 5 plugin that transforms site content into AI-ready formats. It generates clean markdown versions of Twig templates and produces `llms.txt` and `llms-full.txt` files for LLM consumption.

## Development Commands

```bash
# Code style (ECS with Craft CMS rules)
composer check-cs      # Check code style
composer fix-cs        # Fix code style issues

# Static analysis
composer phpstan       # Run PHPStan (level 4)

# Console commands (run from Craft project root)
php craft llmify/markdown/generate  # Generate markdown for all enabled entries
php craft llmify/markdown/clear     # Clear all generated markdown
```

## Architecture

### Plugin Entry Point
`src/Llmify.php` - Registers services, Twig extensions, event listeners, and URL rules. Services are defined in `config()` and accessed as plugin components (e.g., `Llmify::getInstance()->markdown`).

### Services (`src/services/`)
- **MarkdownService** - Converts HTML to markdown using `league/html-to-markdown`, stores results in `llmify_pages` table
- **RefreshService** - Handles markdown regeneration triggered by element changes, uses queue jobs for processing
- **SettingsService** - Manages per-section and per-site configuration stored in `llmify_globals` table
- **RequestService** - Makes async HTTP requests using `amphp/http-client` to fetch rendered pages for markdown conversion

### Twig Extension (`src/twig/`)
Custom Twig tags `{% llmify %}...{% endllmify %}` and `{% excludellmify %}...{% endexcludellmify %}` control what HTML content is included/excluded in markdown output.

### Models & Records
- **Models** (`src/models/`) - Data objects: `Page`, `ContentSettings`, `GlobalSettings`, `PluginSettings`
- **Records** (`src/records/`) - ActiveRecord classes for database tables

### Event-Driven Architecture
The plugin listens to Craft element events (save, delete, restore, move) to automatically queue markdown regeneration. Element changes are tracked via `LlmifyChangedBehavior`.

### URL Routes
Site routes registered for:
- `/llms.txt` - Summary text file
- `/llms-full.txt` - Full content file
- `/{prefix}/<slug>.md` - Individual markdown pages (prefix configurable, default: `raw`)

## Key Dependencies
- `amphp/http-client` - Async HTTP requests for batch processing
- `league/html-to-markdown` - HTML to markdown conversion
- `paquettg/php-html-parser` - DOM manipulation for excluding content by CSS class

## Database Tables
- `llmify_pages` - Stores generated markdown content per entry/site
- `llmify_globals` - Site-level settings
- `llmify_metadata` - Content metadata

## Plugin Settings
Configurable via `config/llmify.php`:
- `isEnabled` - Master toggle
- `markdownUrlPrefix` - URL prefix for markdown files
- `excludeClasses` - CSS classes to exclude from output
- `markdownConfig` - Options passed to HTML-to-markdown converter
