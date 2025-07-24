## Relevant Files

- `src/models/Settings.php` - Contains the plugin's settings model.
- `src/templates/_settings.twig` - The template for the plugin's settings page.
- `src/Llmify.php` - The main plugin class.
- `src/services/LlmifyService.php` - Service to handle content capture and processing.
- `src/twig/LlmifyTokenParser.php` - Twig token parser for the `llmify` tag.
- `src/twig/LlmifyNode.php` - Twig node for the `llmify` tag.

### Notes

- Unit tests should typically be placed alongside the code files they are testing.

## Tasks

- [x] 1.0 Set up Plugin Configuration
  - [x] 1.1 Add a `cacheTtl` public property to the `src/models/Settings.php` model, with a default value of `3600`.
  - [x] 1.2 Add a `TextField` to `src/templates/_settings.twig` for the `cacheTtl` setting, allowing administrators to change its value.
- [x] 2.0 Implement the Core `llmify` Twig Tag Logic
  - [x] 2.1 Create the `src/twig/LlmifyTokenParser.php` file.
  - [x] 2.2 Implement the `parse()` method to handle the `{% llmify %}` and `{% endllmify %}` tags.
  - [x] 2.3 Create the `src/twig/LlmifyNode.php` file.
  - [x] 2.4 Implement the `compile()` method in the `Node` class to render the body content and call the processing service.
- [x] 3.0 Develop the Content Capture and Processing Service
  - [x] 3.1 Create the `src/services/LlmifyService.php` file.
  - [x] 3.2 Implement a `process()` method that takes the rendered HTML as an argument.
  - [x] 3.3 Inside `process()`, generate a cache key from the current request URI.
  - [x] 3.4 Store the HTML content in the Craft cache using the generated key and the configured TTL.
  - [x] 3.5 Log the captured HTML to a dedicated `llmify.log` file.
- [ ] 4.0 Implement Cache Invalidation
  - [x] 4.1 Add a new controller action that clears all cache entries with the `llmify` prefix.
  - [ ] 4.2 Add a button to the `_settings.twig` template that posts to this new controller action.
- [ ] 5.0 Register the Twig Extension and Controller
  - [ ] 5.1 In the main `Llmify.php` plugin file, register the new `LlmifyTokenParser` as a Twig extension.
  - [ ] 5.2 Ensure the service is registered and accessible.
  - [ ] 5.3 Register the controller for the cache invalidation action.
