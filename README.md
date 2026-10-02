<div align="center">
	<a href="https://packagist.org/packages/samuelreichor/craft-llmify" align="center">
      <img src="https://online-images-sr.netlify.app/assets/craft-llmify.png" width="100" alt="Craft LLMify">
	</a>
  <br>
	<h1 align="center">Supercharge Your Craft CMS Content for AI</h1>
  <p align="center">
    LLMify makes your Craft CMS content instantly AI-ready. It transforms your templates into clean, structured outputs, giving you full control over what's included. 
  <br/>
</div>

<p align="center">
  <a href="https://packagist.org/packages/samuelreichor/craft-llmify">
    <img src="https://img.shields.io/packagist/v/samuelreichor/craft-llmify?label=version&color=blue">
  </a>
  <a href="https://packagist.org/packages/samuelreichor/craft-llmify">
    <img src="https://img.shields.io/packagist/dt/samuelreichor/craft-llmify?color=blue">
  </a>
  <a href="https://packagist.org/packages/samuelreichor/craft-llmify">
    <img src="https://img.shields.io/packagist/php-v/samuelreichor/craft-llmify?color=blue">
  </a>
  <a href="https://packagist.org/packages/samuelreichor/craft-llmify">
    <img src="https://img.shields.io/packagist/l/samuelreichor/craft-llmify?color=blue">
  </a>
</p>

## Why LLMify?

AI models like ChatGPT struggle to read websites built for people. They see a wall of code, menus, ads and sidebars, and that noise hides the content that matters.
LLMify turns your Twig templates into clean, structured Markdown and serves it to AI crawlers, agents and anyone asking for it.

Markdown is rendered on demand and cached with Craft's element cache tags, so it is always up to date and blazingly fast.⚡️

## Features

### Content
- **Template-Level Control**: Mark what belongs in the Markdown with the `{% llmify %}` and `{% excludeLlmify %}` Twig tags.
- **CSS Class Exclusion**: Define classes to leave whole parts of the page out of the Markdown.
- **YAML Front Matter**: Configurable metadata with inheritance from site to section to entry.
- **Twig Functions**: `mdUrl()`, `chatGptUrl()` and `claudeUrl()` return an element's Markdown URL and links that open it in ChatGPT or Claude.

### AI Content Delivery
- **Markdown URLs**: Every page is available as Markdown at its URL with `.md` appended, the homepage at `/index.md`.
- **Content Negotiation**: Requests with an `Accept: text/markdown` header get the Markdown of the page.
- **AI Crawler Detection**: Known AI crawlers (GPTBot, ClaudeBot, ChatGPT-User and more) get the Markdown automatically. You can add your own user agents.
- **llms.txt**: Generates `llms.txt` and `/.well-known/llms.txt` as an index of your content.
- **Discovery Tags**: Injects `<link rel="alternate" type="text/markdown">` and `<link rel="describedby" href="/llms.txt">` into your HTML head, as recommended by the [llms.txt spec](https://llmstxt.org/).
- **Response Headers**: Markdown responses send `Vary`, `X-Robots-Tag: noindex, nofollow` and a `Link` header with `rel="canonical"` and `rel="describedby"`.
- **WebMCP**: Optionally exposes your content to in-browser AI agents through the experimental [WebMCP](https://github.com/webmachinelearning/webmcp) standard.

### Caching
- **Always Up to Date**: The cache uses Craft's element cache tags, so related entries, assets and globals invalidate it too.
- **Cache Duration**: The `cacheDuration` setting limits how long a page stays cached.
- **Cache Warming**: `php craft llmify/markdown/generate` caches every page that is not cached yet, e.g. nightly on sites under heavy load. The utility can do the same in a queue job.
- **Clearing**: Clear the cache in the LLMify utility, under Utilities > Clear Caches or with `php craft clear-caches/llmify`.
- **Failure Reporting**: Pages whose Markdown could not be rendered are listed on the dashboard, and the element sidebar shows the reason.

### Content Management
- **Hierarchical Settings**: Site, section and entry level configuration with inheritance.
- **Per-Entry Control**: Include or exclude individual entries with the LLMify Settings field.
- **Element Sidebar**: Shows whether a page is cached, since when and its size in tokens.
- **Dashboard**: Site setup scores, section statistics and cache status at a glance.
- **Permissions**: Granular user permissions for every part of the plugin.
- **Preview Targets**: Preview the Markdown right from the entry editor.

### Integrations
- **SEOmatic**: Populate front matter from SEOmatic fields.
- **Craft Commerce**: Commerce products are supported alongside entries.
- **Blitz**: Requests for Markdown bypass the Blitz cache. This needs Blitz to serve its cache through PHP; when the web server serves the cached files directly, content negotiation and crawler detection can not reach Craft.

### Headless
- **Headless Mode**: For sites where Craft does not render the front end (e.g. a separate Nuxt, Next or Astro app). LLMify fetches your front end URLs (the site's base URL), converts the HTML to Markdown and caches it for `cacheDuration`. Use the exclude classes to leave parts of the page out.
- **Content API**: Pull the Markdown cross-domain so your front end can serve it under its own domain:
  - `GET /actions/llmify/api/llms-txt?site=<handle|id>`
  - `GET /actions/llmify/api/page?uri=<uri>&site=<handle|id>` returns the Markdown of a single page, including front matter.
- **On-Demand Convert**: `POST /actions/llmify/api/convert` with `{ "url": "<front-end URL>" }` converts a single page live. The URL must belong to one of your sites' base URL hosts.
- **API Token**: Protect the API with a token sent in the `X-Llmify-Token` header.

## Requirements

This plugin requires Craft CMS 5.0.0 or later and PHP 8.2 or later.

## Documentation

Visit the [LLMify documentation](https://samuelreichor.at/libraries/craft-llmify) for all documentation, guides, and developer resources.

## Support

If you encounter bugs or have feature requests, [please submit an issue](/../../issues/new). Your feedback helps improve the plugin!
