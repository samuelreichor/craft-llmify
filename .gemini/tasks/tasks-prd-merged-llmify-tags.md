## Relevant Files

- `src/services/MarkdownService.php` - This service will be modified to collect and aggregate content from multiple `llmify` tags.
- `src/twig/LlmifyNode.php` - This Twig node will be updated to send its content to the `MarkdownService` instead of processing it immediately.
- `src/twig/LlmifyTokenParser.php` - This may need adjustments to handle parameters for the combined call.

### Notes

- The initial implementation will focus on aggregating the content. The final processing and rendering will be handled in subsequent tasks.

## Tasks

- [x] 1.0 Modify `MarkdownService` to manage and aggregate `llmify` tag data.
  - [x] 1.1 Add a public array property `contentBlocks` to `MarkdownService` to hold the string content from each `llmify` tag, initialized as an empty array.
  - [x] 1.2 Add a public method `addContentBlock(string $html)` that adds the provided HTML content to the `contentBlocks` array.
  - [x] 1.3 Add a public method `getCombinedHtml()` that concatenates all strings in `contentBlocks` and returns the result.
  - [x] 1.4 Add a public method `clearBlocks()` to reset the `contentBlocks` array, ensuring requests don't share content.
- [ ] 2.0 Modify the Twig extension to use the new aggregation service.
  - [ ] 2.1 Modify `LlmifyNode::compile()` to get an instance of the `MarkdownService`.
  - [ ] 2.2 In `LlmifyNode::compile()`, change the logic to call `Llmify::getInstance()->markdown->addContentBlock($llmifyBody);` instead of `->process(...)`.
  - [ ] 2.3 Remove the line `echo $llmifyBody;` from `LlmifyNode::compile()` to prevent premature output, as rendering will be handled after all tags are processed.
