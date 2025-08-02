# PRD: Merged LLMify Tags

## 1. Introduction/Overview

This document outlines the requirements for a new feature that enhances the `llmify` Twig tag. Currently, each `llmify` tag in a template executes as a separate, independent call to the Large Language Model (LLM). This feature will modify the behavior to collect content from all `llmify` tags within a single template, merge them, and send them to the LLM as a single, consolidated request. The goal is to allow developers to structure their content logically within their HTML while having the LLM process it as one coherent document, improving efficiency and contextual understanding.

## 2. Goals

*   **Improve Efficiency:** Reduce the number of API calls to the LLM by bundling content from multiple `llmify` tags into a single request.
*   **Enhance Context:** Provide the LLM with a more complete context by processing a single, merged document rather than multiple isolated snippets.
*   **Maintain Template Structure:** Allow developers to continue using multiple `llmify` tags to structure their content semantically within the template without changing the final processing logic.

## 3. User Stories

*   **As a developer,** I want to use multiple `llmify` tags throughout my Twig template so that I can keep my content organized within its HTML structure, while having the LLM process all the content together as a single document.
*   **As a content editor,** I want the system to intelligently combine different content sections into one logical request, so the generated text flows better and is more contextually aware.

## 4. Functional Requirements

1.  The system must detect all `llmify` tags rendered within a single request context.
2.  The content within each `llmify` tag must be extracted and concatenated into a single string.
3.  The concatenation must occur in the order that the tags appear in the template's HTML structure.
4.  The parameters (e.g., prompt, model settings) from the **first** `llmify` tag in the template will be used for the single, combined LLM call. Parameters from subsequent tags will be ignored.
5.  A single API call will be made to the LLM with the concatenated content.
6.  The complete response from the LLM must be rendered in the location of the **first** `llmify` tag.
7.  All subsequent `llmify` tags in the same template must render an empty string, as their content has already been processed and included in the first tag's output.

## 5. Non-Goals (Out of Scope)

*   This feature will not merge or combine parameters from multiple `llmify` tags. The first tag's parameters always take precedence.
*   There will be no option to disable this merging behavior. It will become the default and only behavior for `llmify` tags within a template.
*   Nested `llmify` tags are not within the scope of this feature and their behavior is undefined.

## 6. Technical Considerations

*   This will likely require significant changes to the `LlmifyNode` and `LlmifyTokenParser` to coordinate the rendering of multiple tags.
*   A request-level service or cache may be necessary to collect the content and parameters from all `llmify` tags before the template rendering is complete.
*   The implementation must ensure that the final, merged content is output only once and in the correct location.

## 7. Success Metrics

*   A measurable reduction in the number of LLM API calls for templates that contain multiple `llmify` tags.
*   Positive feedback from developers confirming they can structure their templates more logically while achieving a single, coherent LLM output.

## 8. Open Questions

*   Is replacing the first tag and emptying the others the desired final output behavior, or should the output be handled differently?
