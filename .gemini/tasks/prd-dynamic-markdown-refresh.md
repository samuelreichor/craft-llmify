# PRD: Dynamic Markdown Refresh

## 1. Introduction/Overview

This document outlines the requirements for a new feature that automatically triggers markdown regeneration for entries when elements are saved or deleted. The goal is to ensure that all markdown content remains up-to-date dynamically, reflecting changes in related content across the site. This is inspired by the cache-warming logic in the `craft-blitz` plugin.

## 2. Goals

-   **Automated Refresh:** To automatically keep markdown content synchronized with the latest entry data.
-   **Comprehensive Updates:** Ensure that when an element is updated or deleted, all dependent entries are identified and queued for a markdown refresh.
-   **Leverage Existing Infrastructure:** Utilize the existing `RefreshMarkdown.php` queue job and the event listeners already present in `Llmify.php`.
-   **Zero Configuration:** Implement this feature to work out-of-the-box with no need for user configuration.

## 3. User Stories

-   **As a content editor,** when I save an entry, I expect the markdown for that entry and any other entries that reference it to be automatically updated in the background.
-   **As a content editor,** when I update a "Global" set, I expect all relevant entries across the site to have their markdown refreshed to reflect the global changes.
-   **As a content editor,** when I delete an element (e.g., an asset or another entry), I expect any entries that were using it to have their markdown regenerated to remove the obsolete content.
-   **As a developer,** I want this powerful refreshing logic to be enabled by default so I don't have to configure it, ensuring content freshness automatically.

## 4. Functional Requirements

1.  **Event-Driven Trigger:** The system must use the existing element event listeners in `src/Llmify.php` to trigger the refresh logic. The relevant events are:
    -   `Elements::EVENT_AFTER_SAVE_ELEMENT`
    -   `Elements::EVENT_AFTER_RESAVE_ELEMENT`
    -   `Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI`
    -   `Elements::EVENT_AFTER_DELETE_ELEMENT`
    -   `Elements::EVENT_AFTER_RESTORE_ELEMENT`

2.  **Element Identification:** The `RefreshService` will be responsible for identifying which entries to refresh. The logic should be as follows:
    -   **Direct Changes:** If the saved/deleted element is an `Entry` itself, it should be added to the refresh list.
    -   **Relationship-based Changes:** The service must identify all other entries that have a relationship with the saved/deleted element and add them to the refresh list.
    -   **Global Changes:** If the saved/deleted element is a `GlobalSet`, the service must identify all entries in all sections where Llmify is enabled and add them to the refresh list.

3.  **Queueing Refresh Jobs:** For every entry identified in the previous step, the system must push a new `RefreshMarkdown` job to the Craft queue.

4.  **Use Existing Job:** The system must use the existing `src/jobs/RefreshMarkdown.php` for processing the markdown regeneration.

## 5. Non-Goals (Out of Scope)

-   This feature will **not** have a user interface for configuration.
-   This feature will **not** be configurable via a PHP config file (`config/llmify.php`). It will be enabled by default.
-   No new queue jobs will be created; the existing `RefreshMarkdown` job is sufficient.

## 6. Technical Considerations

-   The core logic will be implemented within `src/services/RefreshService.php`.
-   The service will need to perform database queries to find related elements.
-   The existing event handlers in `Llmify.php` will orchestrate the process by calling the `RefreshService`.

## 7. Success Metrics

-   When an element is saved or deleted, the correct set of dependent entries are identified and queued for markdown refresh.
-   The `RefreshMarkdown` jobs are successfully added to the queue and processed without errors.
-   The markdown content for affected entries is correctly updated in the filesystem.

## 8. Open Questions

-   None at this time.
