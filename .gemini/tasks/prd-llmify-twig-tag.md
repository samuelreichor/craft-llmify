# Product Requirements Document: Asynchronous `llmify` Twig Tag

## 1. Introduction/Overview

This document outlines the requirements for a new feature: a `{% llmify %}` Twig tag. The primary purpose of this tag is to capture the rendered HTML output of a specific block within a Twig template. This capture process must not block or alter the original page rendering. The captured HTML will be cached and logged for potential asynchronous or background processing, effectively decoupling content generation from the user-facing request-response cycle.

## 2. Goals

*   **G-1:** Implement a non-blocking Twig tag pair, `{% llmify %}` and `{% endllmify %}`.
*   **G-2:** Ensure the tag renders its content as part of the normal page output without any modification.
*   **G-3:** Capture the fully rendered HTML of the content between the tags.
*   **G-4:** Cache the captured HTML for future use, using the request URI as the cache key.
*   **G-5:** Log the captured HTML to a file for debugging and tracking.
*   **G-6:** Provide a mechanism for administrators to invalidate the entire `llmify` cache.

## 3. User Stories

*   **As a developer,** I want to designate a section of my Twig template to be captured as HTML so that I can process it separately after the page has loaded.
*   **As a developer,** I want the captured content to be cached based on the page URL so that the capture process doesn't need to run on every single request.
*   **As an administrator,** I want to be able to clear the `llmify` cache to force the system to re-capture the content on the next page visit.

## 4. Functional Requirements

*   **FR-1:** The system must provide a new Twig tag pair: `{% llmify %}` and `{% endllmify %}`.
*   **FR-2:** When the template is rendered, the Twig code within the `llmify` block must be executed and its output rendered to the browser as if the `llmify` tags were not present.
*   **FR-3:** The system must capture the final HTML output of the content rendered within the `llmify` block.
*   **FR-4:** The captured HTML must be stored in the Craft CMS data cache.
*   **FR-5:** The cache key must be the request URI, including the path and any query string parameters (e.g., `/products/cool-item?variant=blue`).
*   **FR-6:** The plugin must provide a global configuration setting in its admin panel to define the cache duration (Time-To-Live, in seconds) for all `llmify` blocks.
*   **FR-7:** The captured HTML must be written to a log file for inspection.
*   **FR-8:** The system must provide a mechanism to manually clear all cache entries created by the `llmify` tag. This should be available as a console command and/or a button in the plugin's admin settings.

## 5. Non-Goals (Out of Scope)

*   This feature will **not** automatically inject or update content on the client-side via JavaScript.
*   This feature will **not** provide per-tag configuration for the cache key or TTL. These will be governed by the global rules (URI for key, global config for TTL).
*   The system will **not** version captured content. Each new capture for a given key will overwrite the previous one.
*   The system will **not** provide special handling for user-specific content (like CSRF tokens) within a cached block. The cache key is URL-based and may not be suitable for such content.

## 6. Design Considerations

*   A new field for "Llmify Cache TTL" should be added to the plugin's settings page (`/admin/settings/plugins/llmify`).
*   A "Clear Cache" button should be added to the plugin's settings page to trigger the cache invalidation.

## 7. Technical Considerations

*   The feature will be implemented by creating a custom Twig `TokenParser` and `Node` class within the plugin.
*   The implementation should leverage Craft CMS's standard caching service (`Craft::$app->cache`) and use a consistent prefix for all `llmify`-related cache keys to avoid collisions.
*   Logging should be directed to a dedicated `llmify.log` file using Craft's logging service (`Craft::info(..., 'llmify')`).
*   To ensure the capture process is non-blocking, it should be deferred until after the main response has been prepared, potentially using the `Application::EVENT_AFTER_REQUEST` event.

## 8. Success Metrics

*   Developers can successfully wrap template sections in the `llmify` tag without breaking page rendering.
*   The rendered HTML of tagged sections is correctly captured and can be found in both the Craft cache and the `llmify.log` file.
*   Page load times are not noticeably increased by the presence of the `llmify` tag.
*   The cache can be successfully cleared via the admin panel or console command.

## 9. Open Questions

*   Should the logging be configurable (e.g., enable/disable)? For the initial implementation, it will be enabled by default.
*   What should be the default TTL if not set in the configuration? (Suggestion: 3600 seconds / 1 hour).
