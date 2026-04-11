# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LLMify is a Craft CMS 5 plugin (PHP 8.2+) that transforms site content into AI-ready formats. It generates clean markdown versions of Twig templates, produces `llms.txt` and `llms-full.txt` files for LLM consumption, detects AI bots, and can auto-serve markdown content. Supports both Craft Entries and Commerce Products.

Namespace: `samuelreichor\llmify`

## Development Commands

```bash
# Code style (ECS with Craft CMS rules via ecs.php)
composer check-cs      # Check code style
composer fix-cs        # Fix code style issues

# Static analysis
composer phpstan       # Run PHPStan (level 4)

# Console commands (run from Craft project root)
php craft llmify/markdown/generate  # Generate markdown for all enabled entries
php craft llmify/markdown/clear     # Clear all generated markdown
```

CI runs PHPStan + ECS on every pull request to `main` (`.github/workflows/ci.yml`). No test suite in this repo — testing is handled externally.

## Architecture

### Plugin Entry Point
`src/Llmify.php` (~700 lines) - Registers services, Twig extensions, event listeners, URL rules, permissions, and sidebar panels. Services are defined in `config()` and accessed as plugin components (e.g., `Llmify::getInstance()->markdown`).

### Services (`src/services/`)
Core services:
- **MarkdownService** - Converts HTML to markdown using `league/html-to-markdown`, stores results in `llmify_pages` table
- **RefreshService** - Handles markdown regeneration triggered by element changes, uses queue jobs (`src/jobs/RefreshMarkdownJob.php`)
- **SettingsService** - Manages per-section and per-site configuration stored in `llmify_globals` table
- **RequestService** - Makes async HTTP requests using `amphp/http-client` to fetch rendered pages for markdown conversion

Supporting services:
- **BotDetectionService** - Detects AI crawlers (GPTBot, ClaudeBot, ChatGPT-User, etc.) via user-agent
- **DashboardService** - Calculates site setup scores and section statistics for the CP dashboard
- **FieldDiscoveryService** - Discovers relevant fields (including SEOmatic) for markdown generation
- **FrontMatterService** - Generates YAML front matter from SEOmatic fields and metadata
- **HelperService** - Utility functions (markdown URL generation, feature availability checks)
- **LlmsService** - Generates `llms.txt` and `llms-full.txt` content
- **MetadataService** - Manages element metadata storage and retrieval
- **PermissionService** - Permission management (Pro Edition only)

### Key Components
- **Twig Extension** (`src/twig/`) - Custom tags `{% llmify %}...{% endllmify %}` and `{% excludellmify %}...{% endexcludellmify %}` control what HTML is included/excluded in markdown output
- **LlmifySettingsField** (`src/fields/`) - Custom Craft field for per-entry markdown control
- **LlmifyChangedBehavior** (`src/behaviors/`) - Attached to elements to track changes and trigger refresh
- **SiteUrlBatcher** (`src/batchers/`) - Collects URLs in batches for async processing
- **Constants** (`src/Constants.php`) - Database table names, permission constants, and `X-Llmify-Refresh-Request` header

### Models & Records
- **Models** (`src/models/`) - Data objects: `Page`, `ContentSettings`, `GlobalSettings`, `PluginSettings`
- **Records** (`src/records/`) - ActiveRecord classes for `llmify_pages`, `llmify_metadata`, `llmify_globals`

### Event-Driven Architecture
The plugin listens to Craft element events (save, delete, restore, move) to automatically queue markdown regeneration. Element changes are tracked via `LlmifyChangedBehavior`. Auto-serve mode intercepts template rendering to return markdown instead of HTML when an `Accept: text/markdown` header is present or an AI bot is detected.

### URL Routes
Site routes:
- `/llms.txt` and `/.well-known/llms.txt` - Summary text file
- `/llms-full.txt` - Full content file
- `/{prefix}/<slug>.md` - Individual markdown pages (prefix configurable, default: `raw`)

CP routes under `llmify/`: dashboard, content (per-section settings), globals (per-site settings), settings (plugin config).

### Commerce Support
Soft dependency on Craft Commerce — supports Product elements alongside Entries. Commerce-related PHPStan errors are ignored in `phpstan.neon`.

## Key Dependencies
- `amphp/http-client` - Async HTTP requests for batch processing
- `league/html-to-markdown` - HTML to markdown conversion
- `paquettg/php-html-parser` - DOM manipulation for excluding content by CSS class

## Database Tables
Defined in `src/Constants.php` and created by `src/migrations/Install.php`:
- `llmify_pages` - Stores generated markdown content per element/site (supports entries and products via `elementType` column)
- `llmify_globals` - Site-level settings
- `llmify_metadata` - Content metadata per group/element type

## Plugin Settings
Configurable via `config/llmify.php` (see `src/models/PluginSettings.php` for all options):
- `isEnabled` - Master toggle
- `markdownUrlPrefix` - URL prefix for markdown files (default: `raw`)
- `excludeClasses` - CSS classes to exclude from output
- `markdownConfig` - Options passed to HTML-to-markdown converter
- `enableAutoServe` - Serve markdown on `Accept: text/markdown` header
- `enableBotDetection` - Auto-detect AI crawlers and serve markdown
- `enableDiscoveryTag` - Inject `<link rel="alternate" type="text/markdown">` tags
- `enableSeomatic` - Pull front matter from SEOmatic fields
- `concurrentRequests` / `requestTimeout` - Async request tuning
