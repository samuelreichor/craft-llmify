<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\elements\Entry;
use samuelreichor\llmify\fields\LlmifySettingsField;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\Page;

class FrontMatterService extends Component
{
    /**
     * @var array<string, array{label: string, getValue: callable}>
     */
    private array $builtInFields = [];

    public function init(): void
    {
        parent::init();
        $this->registerBuiltInFields();
    }

    private function registerBuiltInFields(): void
    {
        $this->builtInFields = [
            'title' => [
                'label' => 'Title',
                'getValue' => fn(Page $page, ?Entry $entry) => $page->title,
            ],
            'description' => [
                'label' => 'Description',
                'getValue' => fn(Page $page, ?Entry $entry) => $page->description,
            ],
            'url' => [
                'label' => 'URL',
                'getValue' => fn(Page $page, ?Entry $entry) => $page->entryMeta['fullUrl'] ?? '',
            ],
            'uri' => [
                'label' => 'URI',
                'getValue' => fn(Page $page, ?Entry $entry) => $page->entryMeta['uri'] ?? '',
            ],
            'date_modified' => [
                'label' => 'Date Modified',
                'getValue' => fn(Page $page, ?Entry $entry) => $entry?->dateUpdated?->format('c') ?? '',
            ],
            'date_created' => [
                'label' => 'Date Created',
                'getValue' => fn(Page $page, ?Entry $entry) => $entry?->dateCreated?->format('c') ?? '',
            ],
            'section' => [
                'label' => 'Section',
                'getValue' => fn(Page $page, ?Entry $entry) => $entry?->section?->name ?? '',
            ],
            'entry_type' => [
                'label' => 'Entry Type',
                'getValue' => fn(Page $page, ?Entry $entry) => $entry?->type?->name ?? '',
            ],
            'author' => [
                'label' => 'Author',
                'getValue' => fn(Page $page, ?Entry $entry) => $this->getAuthorName($entry),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableBuiltInFields(): array
    {
        $fields = [];
        foreach ($this->builtInFields as $handle => $config) {
            $fields[$handle] = $config['label'];
        }
        return $fields;
    }

    /**
     * Resolve front matter fields using inheritance: Site -> Section -> Entry
     *
     * @param Entry $entry
     * @return array
     */
    public function resolveFrontMatterFields(Entry $entry): array
    {
        $siteId = $entry->siteId;
        $sectionId = $entry->sectionId;

        // 1. Site-Level Defaults
        $globalSettings = Llmify::getInstance()->settings->getGlobalSetting($siteId);
        $fields = $globalSettings->frontMatterFields;

        // 2. Section-Level Override?
        $contentSettings = Llmify::getInstance()->settings->getContentSetting($sectionId, $siteId);
        if ($contentSettings->overrideFrontMatter && !empty($contentSettings->frontMatterFields)) {
            $fields = $contentSettings->frontMatterFields;
        }

        // 3. Entry-Level Override?
        $entrySettingsField = Llmify::getInstance()->helper->getFieldOfTypeFromEntry($entry, LlmifySettingsField::class);
        if ($entrySettingsField) {
            $fieldData = $entry->getFieldValue($entrySettingsField->handle);
            if (is_array($fieldData) && ($fieldData['overrideFrontMatterSettings'] ?? false)) {
                $fields = $fieldData['frontMatterFields'] ?? [];
            }
        }

        return $fields;
    }

    public function generateFrontMatter(Page $page, ?Entry $entry): string
    {
        // If no entry, we cannot resolve fields with inheritance
        if ($entry === null) {
            return '';
        }

        $fields = $this->resolveFrontMatterFields($entry);

        // If empty fields array, no front matter output
        if (empty($fields)) {
            return '';
        }

        $data = [];

        foreach ($fields as $field) {
            $enabled = $field['enabled'] ?? false;
            // Craft stores lightswitch values as '1'/'' or true/false depending on context
            if ($enabled !== true && $enabled !== '1' && $enabled !== 1) {
                continue;
            }

            $handle = $field['handle'] ?? '';
            $label = $field['label'] ?? '';

            if ($handle === '' || $label === '') {
                continue;
            }

            // Parse handle prefix
            if (str_starts_with($handle, 'builtin:')) {
                $builtinHandle = substr($handle, 8); // Remove 'builtin:' prefix
                if (isset($this->builtInFields[$builtinHandle])) {
                    $value = ($this->builtInFields[$builtinHandle]['getValue'])($page, $entry);
                    if ($value !== '' && $value !== null) {
                        $data[$label] = $value;
                    }
                }
            } elseif (str_starts_with($handle, 'field:')) {
                $fieldHandle = substr($handle, 6); // Remove 'field:' prefix
                $value = $this->getEntryFieldValue($entry, $fieldHandle);
                if ($value !== null && $value !== '') {
                    $data[$label] = $this->formatValue($value);
                }
            }
        }

        if (empty($data)) {
            return '';
        }

        return $this->toYaml($data);
    }

    /**
     * Get value from entry field, supporting dot notation for content blocks
     */
    private function getEntryFieldValue(Entry $entry, string $fieldHandle): mixed
    {
        // Check for dot notation (content block fields)
        if (str_contains($fieldHandle, '.')) {
            $parts = explode('.', $fieldHandle, 2);
            $contentBlockHandle = $parts[0];
            $nestedFieldHandle = $parts[1];

            if (!$entry->hasProperty($contentBlockHandle)) {
                return null;
            }

            $contentBlock = $entry->$contentBlockHandle;
            if ($contentBlock === null) {
                return null;
            }

            // Content block is an object with properties
            if (is_object($contentBlock) && property_exists($contentBlock, $nestedFieldHandle)) {
                return $contentBlock->$nestedFieldHandle;
            }

            // Try as array access
            if (is_array($contentBlock) && isset($contentBlock[$nestedFieldHandle])) {
                return $contentBlock[$nestedFieldHandle];
            }

            // Try getter method
            if (is_object($contentBlock) && method_exists($contentBlock, 'getFieldValue')) {
                return $contentBlock->getFieldValue($nestedFieldHandle);
            }

            return null;
        }

        // Simple field
        if (!$entry->hasProperty($fieldHandle)) {
            return null;
        }

        return $entry->$fieldHandle;
    }

    public function prependFrontMatter(string $markdown, Page $page, ?Entry $entry): string
    {
        $frontMatter = $this->generateFrontMatter($page, $entry);

        if ($frontMatter === '') {
            return $markdown;
        }

        return $frontMatter . $markdown;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function toYaml(array $data): string
    {
        $lines = ['---'];

        foreach ($data as $key => $value) {
            $lines[] = $this->formatYamlLine($key, $value);
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function formatYamlLine(string $key, mixed $value): string
    {
        if (is_bool($value)) {
            return sprintf('%s: %s', $key, $value ? 'true' : 'false');
        }

        if (is_int($value) || is_float($value)) {
            return sprintf('%s: %s', $key, $value);
        }

        if (is_array($value)) {
            return sprintf('%s: %s', $key, $this->escapeYamlString(json_encode($value)));
        }

        return sprintf('%s: %s', $key, $this->escapeYamlString((string)$value));
    }

    private function escapeYamlString(string $value): string
    {
        // Check if the string needs quoting
        $needsQuoting = preg_match('/[:\{\}\[\],&\*#\?|\-<>=!%@`]/', $value) ||
            str_contains($value, "\n") ||
            str_starts_with($value, ' ') ||
            str_ends_with($value, ' ') ||
            $value === '' ||
            is_numeric($value) ||
            in_array(strtolower($value), ['true', 'false', 'null', 'yes', 'no', 'on', 'off'], true);

        if (!$needsQuoting) {
            return $value;
        }

        // Escape double quotes and backslashes
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return '"' . $escaped . '"';
    }

    private function getAuthorName(?Entry $entry): string
    {
        $author = $entry?->getAuthor();

        if ($author === null) {
            return '';
        }

        $fullName = $author->fullName;

        if ($fullName !== null && $fullName !== '') {
            return $fullName;
        }

        return $author->username ?? '';
    }

    private function formatValue(mixed $value): mixed
    {
        if ($value instanceof \DateTime) {
            return $value->format('c');
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        if (is_scalar($value) || is_array($value)) {
            return $value;
        }

        return null;
    }
}
