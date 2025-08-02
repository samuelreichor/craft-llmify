## Relevant Files

- `src/services/RefreshService.php` - This service will contain the core logic for identifying and refreshing entries.
- `src/jobs/RefreshMarkdown.php` - This existing job will be pushed to the queue to perform the actual markdown generation.
- `src/Llmify.php` - The main plugin file that already contains the event listeners that will trigger the `RefreshService`.
- `tests/unit/RefreshServiceTest.php` - A new file for unit testing the `RefreshService`.

### Notes

- We will need to create unit tests to ensure the logic is working as expected.

## Tasks

- [x] 1.0 Implement the core logic in `RefreshService` to handle element changes.
  - [x] 1.1 Modify the `addElement` method in `RefreshService` to remove the `dd('add to queue')` placeholder and prepare for the new logic.
  - [x] 1.2 In `addElement`, add the element to a list of entries to be refreshed if it's an instance of `craft\elements\Entry`.
  - [x] 1.3 Ensure the `isRefreshableElement` check is performed at the beginning of the `addElement` method.
- [x] 2.0 Develop the relationship detection mechanism to find entries linked to a modified element.
  - [x] 2.1 Create a new private method `_findRelatedEntries(ElementInterface $element)` in `RefreshService`.
  - [x] 2.2 Implement a Craft element query within `_findRelatedEntries` to find all `Entry` elements that have a relational field pointing to the given `$element`.
  - [x] 2.3 Call `_findRelatedEntries` from the `addElement` method and add the returned entries to the list of entries to be refreshed.
- [x] 3.0 Implement the logic to handle `GlobalSet` modifications, triggering a widespread refresh.
  - [x] 3.1 In the `addElement` method, add a check to see if the element is an instance of `craft\elements\GlobalSet`.
  - [x] 3.2 If it is a `GlobalSet`, create a new private method `_findAllRefreshableEntries()` that retrieves all entries from sections where Llmify is enabled.
  - [x] 3.3 Add the entries returned from `_findAllRefreshableEntries` to the refresh list.
- [x] 4.0 Integrate the `RefreshService` with Craft's queue system to dispatch `RefreshMarkdown` jobs.
  - [x] 4.1 At the end of the `addElement` method, iterate over the unique list of entries that need refreshing.
  - [x] 4.2 For each entry, push a new `samuelreichor\llmify\jobs\RefreshMarkdown` job onto the Craft queue.
  - [x] 4.3 Ensure that no duplicate jobs are pushed for the same entry.
- [x] 5.0 Conduct end-to-end testing to validate the entire refresh process.
  - [x] 5.1 Create a test entry and verify that saving it triggers a `RefreshMarkdown` job for itself.
  - [x] 5.2 Create two entries, relate them, and verify that updating one triggers a refresh job for both.
  - [x] 5.3 Update a `GlobalSet` and verify that refresh jobs are queued for all expected entries.
  - [x] 5.4 Delete an element and verify that related entries are refreshed.
