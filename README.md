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

AI models like ChatGPT struggle to read websites built for people. They see a wall of code, menus, ads, and sidebars. This "noise" makes it hard for them to find the real story - your valuable story.
LLMify solves this by converting your Twig templates into clean, structured Markdown.

LLMify is built for production-scale AI content delivery.
Instead of converting HTML to Markdown on every request, LLMify does the work upfront. Your Markdown is stored and ready before any bot shows up.
Combined with Craft Commerce compatibility, granular control over your Markdowns and user permissions, LLMify gives you everything you need to make your entire site AI-ready.

## Features

### Content Generation
- **Pre-Generated Markdown**: Async batch processing with [amphp](https://amphp.org/) stores Markdown in a dedicated database table for instant delivery at any scale.
- **On-Demand Fallback**: Automatically generates Markdown on first request if not yet pre-generated.
- **Template-Level Control**: Use `{% llmify %}` and `{% excludellmify %}` Twig tags for precise control over what content is included in your Markdown output.
- **CSS Class Exclusion**: Define classes to exclude entire sections from the HTML-to-Markdown conversion.
- **YAML Front Matter**: Configurable metadata with hierarchical inheritance (Site > Section > Entry).
- **Console Commands**: `llmify/markdown/generate` and `llmify/markdown/clear` for CI/CD and deployment workflows.

### AI Content Delivery
- **Auto-Serve Markdown**: Content negotiation via `Accept: text/markdown` header.
- **AI Crawler Detection**: Automatically serve Markdown to known AI bots (GPTBot, ClaudeBot, ChatGPT-User, and more).
- **LLM-Ready Text Files**: Generates `llms.txt`, `llms-full.txt`, and `/.well-known/llms.txt`.
- **Discovery Tag**: Injects `<link rel="alternate" type="text/markdown">` into your HTML head.
- **Industry Standard Response Headers**: Sets `Vary: Accept` (+ `User-Agent` for auto-serve), `X-Robots-Tag: noindex, nofollow`, and `Link: rel="canonical"` on all Markdown responses.

### Content Management
- **Hierarchical Settings**: Site-wide, section, and entry-level configuration with inheritance.
- **Per-Entry Control**: Include or exclude individual entries via the LLMify Settings Field.
- **Permission System**: Granular user permissions for everything.
- **Preview Targets**: Preview Markdown output directly from the entry editor.
- **Dashboard**: Site setup scores and section-level content statistics at a glance.

### Integrations
- **SEOmatic Integration**: Automatically populate front matter from SEOmatic fields.
- **Craft Commerce Support**: Full support for Commerce Products alongside Entries.

## Requirements

This plugin requires Craft CMS 5.0.0 or later, and PHP 8.2 or later.

## Documentation

Visit the [LLMify documentation](https://samuelreichor.at/libraries/craft-llmify) for all documentation, guides, and developer resources.

## Support

If you encounter bugs or have feature requests, [please submit an issue](/../../issues/new). Your feedback helps improve the plugin!
