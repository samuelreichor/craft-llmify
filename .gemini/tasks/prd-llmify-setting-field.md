# Product Requirements Document: LLMify Settings Overhaul

## 1. Introduction/Overview

This document outlines the requirements for a new settings structure within the LLMify plugin. The goal is to provide granular control over how LLM-related metadata (specifically titles and descriptions) is generated and managed. This will be achieved by introducing two new settings pages ("Globals" and "Content") and a new "LLMify Settings" field that can be added to entries to override default configurations. This feature aims to give content editors the power to define both broad, site-wide defaults and highly specific, entry-level overrides for LLM-related content.

## 2. Goals

*   Provide a centralized place for global settings like Cache TTL.
*   Create a user-friendly interface to manage default LLM titles and descriptions for different content sections.
*   Allow content editors to dynamically map entry fields as the source for default titles and descriptions.
*   Enable per-entry overrides of section-level settings for maximum flexibility.
*   Ensure a clear and logical fallback hierarchy for settings.

## 3. User Stories

*   **As a developer,** I want to configure global settings for the LLMify plugin, such as cache duration, in a single, easy-to-find location.
*   **As a content manager,** I want to define default title and description structures for all entries within a specific section (e.g., "Blog Posts," "News Articles") to ensure consistency and reduce manual work.
*   **As a content editor,** I want to override the default section-level title and description for a specific entry to optimize it for a particular keyword or promotional campaign.

## 4. Functional Requirements

### FR1: New Settings Pages

1.  Create two new sub-navigation items under the main "Llmify" settings area:
    *   **Globals:** For site-wide configurations.
    *   **Content:** For section-specific configurations.

### FR2: Globals Settings Page

1.  The "Globals" page shall contain the following fields:
    *   **Cache TTL:** A number input field to define the cache duration in seconds.
    *   **LLM Title:** A text input field. This value is intended for use at the top of the `llms.txt` and `full-llms.txt` files and operates independently of entry-specific settings.
    *   **LLM Description:** A text input field, also for use at the top of `llms.txt` and `full-llms.txt`.

### FR3: Content Settings Page - Section Overview

1.  The "Content" page shall display a list of all entry sections in the system.
2.  Only sections that have a URL format defined (i.e., are accessible on the front-end) should be listed.
3.  Each section in the list should be a link to a detail page for that section's settings.

### FR4: Content Settings Page - Section Detail View

1.  On clicking a section link, the user is taken to a detail page for that section.
2.  This page shall contain the following fields:
    *   **Entry Default Title:** A field to define the default title for entries in this section.
    *   **Entry Default Description:** A field to define the default description for entries in this section.
3.  For both the Title and Description fields, the user must be able to choose between two modes:
    *   **Custom Text:** A simple text input to write a static default value.
    *   **Use Field Value:** A dropdown menu to select a field from the entry.
4.  The dropdown menu for "Use Field Value" must only list fields that meet the following criteria:
    *   The field must be a **Plain Text** field.
    *   The field must exist in **all** of the entry types assigned to the current section.

### FR5: New "LLMify Settings" Field

1.  Create a new custom field type named "LLMify Settings".
2.  This field can be added to any Entry Type via the standard Craft CMS field layout editor.
3.  When added to an entry, this field shall display two text input fields:
    *   **Title:** For overriding the section-level default title.
    *   **Description:** For overriding the section-level default description.

### FR6: Settings Fallback Logic

1.  When determining the title for a given entry, the system must use the following order of precedence:
    1.  The value in the "Title" field of the "LLMify Settings" field on the entry itself.
    2.  The "Entry Default Title" defined for the entry's section (either custom text or the value from the selected entry field).
2.  When determining the description for a given entry, the system must use the following order of precedence:
    1.  The value in the "Description" field of the "LLMify Settings" field on the entry itself.
    2.  The "Entry Default Description" defined for the entry's section (either custom text or the value from the selected entry field).
3.  If a content editor fills out only the "Title" in the "LLMify Settings" field on an entry, the description must fall back to the section default. The same logic applies in reverse if only the description is filled out.

## 5. Non-Goals (Out of Scope)

*   This feature will not involve generating content via an LLM; it is focused solely on the settings framework.
*   Support for field types other than "Plain Text" for the dynamic value mapping is not included in this phase.
*   The "LLMify Settings" field will not be available for anything other than Entries (e.g., not for Categories, Users, etc.).
*   There will be no versioning or history tracking for changes made in these settings pages.

## 6. Design Considerations

*   The new settings pages should adopt the standard Craft CMS control panel styling and layout to ensure a consistent user experience.
*   The UI for choosing between "Custom Text" and "Use Field Value" should be intuitive. A dropdown menu that includes a "Custom Text..." option as the first item is the preferred implementation (as per user feedback).

## 7. Open Questions

*   Should there be a permission setting to control which user groups can access the new "Globals" and "Content" settings pages?
