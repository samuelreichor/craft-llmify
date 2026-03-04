<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\base\ElementInterface;
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
                'getValue' => fn(Page $page, ?ElementInterface $element) => $page->title,
            ],
            'description' => [
                'label' => 'Description',
                'getValue' => fn(Page $page, ?ElementInterface $element) => $page->description,
            ],
            'url' => [
                'label' => 'URL',
                'getValue' => fn(Page $page, ?ElementInterface $element) => $page->elementMeta['fullUrl'] ?? '',
            ],
            'uri' => [
                'label' => 'URI',
                'getValue' => fn(Page $page, ?ElementInterface $element) => $page->elementMeta['uri'] ?? '',
            ],
            'date_modified' => [
                'label' => 'Date Modified',
                'getValue' => fn(Page $page, ?ElementInterface $element) => $element?->dateUpdated?->format('c') ?? '',
            ],
            'date_created' => [
                'label' => 'Date Created',
                'getValue' => fn(Page $page, ?ElementInterface $element) => $element?->dateCreated?->format('c') ?? '',
            ],
            'section' => [
                'label' => 'Section',
                'getValue' => fn(Page $page, ?ElementInterface $element) => $this->getSectionName($element),
            ],
            'entry_type' => [
                'label' => 'Entry Type',
                'getValue' => fn(Page $page, ?ElementInterface $element) => $this->getTypeName($element),
            ],
            'author' => [
                'label' => 'Author',
                'getValue' => fn(Page $page, ?ElementInterface $element) => $this->getAuthorName($element),
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
     * @param ElementInterface $element
     * @return array
     */
    public function resolveFrontMatterFields(ElementInterface $element): array
    {
        $siteId = $element->siteId;
        $groupId = HelperService::getGroupIdForElement($element);
        $elementType = HelperService::getElementTypeForElement($element);

        // 1. Site-Level Defaults
        $globalSettings = Llmify::getInstance()->settings->getGlobalSetting($siteId);
        $fields = $globalSettings->frontMatterFields;

        // 2. Section/Group-Level Override?
        $contentSettings = Llmify::getInstance()->settings->getContentSetting($groupId, $siteId, $elementType);
        if ($contentSettings->overrideFrontMatter && !empty($contentSettings->frontMatterFields)) {
            $fields = $contentSettings->frontMatterFields;
        }

        // 3. Entry-Level Override? (via LlmifySettingsField)
        $entrySettingsField = Llmify::getInstance()->helper->getFieldOfTypeFromElement($element, LlmifySettingsField::class);
        if ($entrySettingsField) {
            $fieldData = $element->getFieldValue($entrySettingsField->handle);
            if (is_array($fieldData) && ($fieldData['overrideFrontMatterSettings'] ?? false)) {
                $fields = $fieldData['frontMatterFields'] ?? [];
            }
        }

        return $fields;
    }

    public function generateFrontMatter(Page $page, ?ElementInterface $element): string
    {
        // If no element, we cannot resolve fields with inheritance
        if ($element === null) {
            return '';
        }

        $fields = $this->resolveFrontMatterFields($element);

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
                    $value = ($this->builtInFields[$builtinHandle]['getValue'])($page, $element);
                    if ($value !== '' && $value !== null) {
                        $data[$label] = $value;
                    }
                }
            } elseif (str_starts_with($handle, 'field:')) {
                $fieldHandle = substr($handle, 6); // Remove 'field:' prefix
                $value = $this->getElementFieldValue($element, $fieldHandle);
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
     * Get value from element field, supporting dot notation for content blocks
     */
    private function getElementFieldValue(ElementInterface $element, string $fieldHandle): mixed
    {
        // Check for dot notation (content block fields)
        if (str_contains($fieldHandle, '.')) {
            $parts = explode('.', $fieldHandle, 2);
            $contentBlockHandle = $parts[0];
            $nestedFieldHandle = $parts[1];

            if (!$element->hasProperty($contentBlockHandle)) {
                return null;
            }

            $contentBlock = $element->$contentBlockHandle;
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
        if (!$element->hasProperty($fieldHandle)) {
            return null;
        }

        return $element->$fieldHandle;
    }

    public function prependFrontMatter(string $markdown, Page $page, ?ElementInterface $element): string
    {
        $frontMatter = $this->generateFrontMatter($page, $element);

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

    private function getSectionName(?ElementInterface $element): string
    {
        if ($element instanceof Entry) {
            return $element->section?->name ?? '';
        }

        if (HelperService::isCommerceInstalled() && $element instanceof \craft\commerce\elements\Product) {
            return $element->getType()->name ?? '';
        }

        return '';
    }

    private function getTypeName(?ElementInterface $element): string
    {
        if ($element instanceof Entry) {
            return $element->type?->name ?? '';
        }

        if (HelperService::isCommerceInstalled() && $element instanceof \craft\commerce\elements\Product) {
            return $element->getType()->name ?? '';
        }

        return '';
    }

    private function getAuthorName(?ElementInterface $element): string
    {
        if ($element === null) {
            return '';
        }

        if (!method_exists($element, 'getAuthor')) {
            return '';
        }

        $author = $element->getAuthor();

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
