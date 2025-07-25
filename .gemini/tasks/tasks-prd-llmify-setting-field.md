## Relevant Files

- `src/models/Settings.php` - Will be modified to include the new settings properties.
- `src/Llmify.php` - The main plugin file, will be modified to register new controllers, settings pages, and the custom field.
- `src/controllers/GlobalsController.php` - New controller for the "Globals" settings page.
- `src/controllers/ContentController.php` - New controller for the "Content" settings page.
- `src/templates/settings/globals/index.twig` - New template for the "Globals" settings UI.
- `src/templates/settings/content/index.twig` - New template for the "Content" settings section overview.
- `src/templates/settings/content/edit.twig` - New template for the section-specific settings detail view.
- `src/fields/LlmifySettingsField.php` - New custom field for entry-level overrides.
- `src/services/MetadataService.php` - New service to handle the fallback logic for retrieving titles and descriptions.
- `src/twig/LlmifyExtension.php` - Will be updated to use the new `MetadataService`.

### Notes

- Follow Craft CMS conventions for file naming and structure.
- Ensure all new classes are properly namespaced.
- After implementing a feature, clear the Craft CMS cache to ensure changes are reflected.

## Tasks

- [x] 1.0 Set Up Core Infrastructure for New Settings
  - [x] 1.1 Update `Settings.php` model to include new properties for Globals (`cacheTtl`, `llmTitle`, `llmDescription`) and Content (`contentSettings`).
  - [x] 1.2 Modify `Llmify.php` to register the new "Globals" and "Content" settings pages as sub-navigation items.
  - [x] 1.3 Create new controller files: `GlobalsController.php` and `ContentController.php` in the `src/controllers` directory.
- [ ] 2.0 Implement the "Globals" Settings Page
  - [x] 2.1 In `GlobalsController.php`, create an `actionIndex()` that renders a template.
  - [x] 2.2 Create the `src/templates/settings/globals/index.twig` template.
  - [x] 2.3 Add the "Cache TTL", "LLM Title", and "LLM Description" fields to the Globals template, linking them to the `Settings.php` model.
  - [x] 2.4 Implement the save logic in `Llmify.php` or a dedicated controller action to persist the global settings.
- [ ] 3.0 Implement the "Content" Settings Page
  - [ ] 3.1 In `ContentController.php`, create an `actionIndex()` that fetches all sections with a URL and passes them to a template.
  - [ ] 3.2 Create the `src/templates/settings/content/index.twig` template to display the list of sections.
  - [ ] 3.3 In `ContentController.php`, create an `actionEditSection(int $sectionId)` to handle the detail view.
  - [ ] 3.4 Create the `src/templates/settings/content/edit.twig` template for the section detail view.
  - [ ] 3.5 Implement the UI in `edit.twig` for "Entry Default Title" and "Description" with the "Custom Text" / "Use Field Value" dropdown.
  - [ ] 3.6 In `actionEditSection`, fetch all plain text fields that are common to all entry types of that section and pass them to the template.
  - [ ] 3.7 Implement the save logic for the content settings.
- [ ] 4.0 Create the "LLMify Settings" Custom Field
  - [ ] 4.1 Create a new file `src/fields/LlmifySettingsField.php`.
  - [ ] 4.2 Implement the `LlmifySettingsField` class, extending `craft\base\Field`.
  - [ ] 4.3 Define the field's `getInputHtml()` method to render two text inputs for "Title" and "Description".
  - [ ] 4.4 Register the new field type in `Llmify.php` so it appears in the field layout editor.
- [ ] 5.0 Implement the Settings Fallback Logic
  - [ ] 5.1 Create a new service, e.g., `MetadataService.php`, to encapsulate the fallback logic.
  - [ ] 5.2 In the `MetadataService`, create a method `getEntryTitle(Entry $entry)` that implements the logic from FR6.
  - [ ] 5.3 Create a similar method `getEntryDescription(Entry $entry)`.
  - [ ] 5.4 Update the Twig extension (`LlmifyExtension.php`) to use this new service when retrieving title and description.
