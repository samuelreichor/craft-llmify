<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use craft\fields\ContentBlock;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\Section;
use samuelreichor\llmify\Llmify;

class HelperService extends Component
{
    private const textFieldOptionBase = [
        [
            'label' => 'Custom Text',
            'value' => 'custom',
        ],
        [
            'label' => '--- Fields ---',
            'disabled' => true,
        ],
        [
            'label' => 'Title',
            'value' => 'title',
        ],
    ];
    /**
     * @throws SiteNotFoundException
     */
    public function getCurrentCpSiteId(): ?int
    {
        $site = null;
        $siteHandle = Craft::$app->getRequest()->getQueryParam('site');

        $sites = Craft::$app->getSites();
        if ($siteHandle) {
            $site = $sites->getSiteByHandle($siteHandle);
        }

        if (!$site) {
            $site = $sites->getCurrentSite();
        }

        return $site->id;
    }

    public function getTextFieldsForSection(Section $section): array
    {
        $entryTypes = $section->getEntryTypes();
        $textFields = $this->getCommonTextFieldsForEntryTypes($entryTypes);
        $contentBlockFields = $this->getTextFieldsInContentBlocks($entryTypes);

        $result = array_merge(self::textFieldOptionBase, $textFields);

        if (!empty($contentBlockFields)) {
            $result[] = [
                'label' => '--- Content Blocks ---',
                'disabled' => true,
            ];
            $result = array_merge($result, $contentBlockFields);
        }

        return $result;
    }

    public function getTextFieldsForEntry(Entry $entry): array
    {
        $entryTypes = [$entry->type];
        $textFields = $this->getCommonTextFieldsForEntryTypes($entryTypes);
        $contentBlockFields = $this->getTextFieldsInContentBlocks($entryTypes);

        $result = array_merge(self::textFieldOptionBase, $textFields);

        if (!empty($contentBlockFields)) {
            $result[] = [
                'label' => '--- Content Blocks ---',
                'disabled' => true,
            ];
            $result = array_merge($result, $contentBlockFields);
        }

        return $result;
    }

    /**
     * @param EntryType[] $entryTypes
     * @return array<int, array{label: string, value: string}>
     */
    private function getTextFieldsInContentBlocks(array $entryTypes): array
    {
        if (empty($entryTypes) || !class_exists(ContentBlock::class)) {
            return [];
        }

        $allContentBlockFields = [];

        foreach ($entryTypes as $entryType) {
            $fields = $entryType->getCustomFields();
            $contentBlockFieldsInType = [];

            foreach ($fields as $field) {
                if (!$field instanceof ContentBlock) {
                    continue;
                }

                $nestedFields = $field->getFieldLayout()->getCustomFields();
                foreach ($nestedFields as $nestedField) {
                    $isCkEditorField = class_exists('\craft\ckeditor\Field') && is_a($nestedField, '\craft\ckeditor\Field');
                    if ($nestedField instanceof PlainText || $isCkEditorField) {
                        $key = $field->handle . '.' . $nestedField->handle;
                        $contentBlockFieldsInType[$key] = [
                            'contentBlockName' => $field->name,
                            'fieldName' => $nestedField->name,
                        ];
                    }
                }
            }

            $allContentBlockFields[] = $contentBlockFieldsInType;
        }

        if (count($allContentBlockFields) === 1) {
            $commonKeys = array_keys($allContentBlockFields[0]);
        } else {
            $commonKeys = array_keys(array_intersect_key(...$allContentBlockFields));
        }
        $result = [];

        foreach ($commonKeys as $key) {
            $info = $allContentBlockFields[0][$key];
            $result[] = [
                'label' => $info['contentBlockName'] . ' › ' . $info['fieldName'],
                'value' => $key,
            ];
        }

        return $result;
    }

    /**
     * @param EntryType[] $entryTypes
     * @return array
     */
    private function getCommonTextFieldsForEntryTypes(array $entryTypes): array
    {
        if (empty($entryTypes)) {
            return [];
        }

        $allFieldHandles = [];
        foreach ($entryTypes as $entryType) {
            $fields = $entryType->getCustomFields();
            $textFields = array_filter($fields, function($field) {
                $isCkEditorField = class_exists('\craft\ckeditor\Field') && is_a($field, '\craft\ckeditor\Field');
                return $field instanceof PlainText || $isCkEditorField;
            });
            $allFieldHandles[] = array_map(function($field) {
                return $field->handle;
            }, $textFields);
        }

        $commonFieldHandles = array_intersect(...$allFieldHandles);
        $commonTextFields = [];

        foreach ($commonFieldHandles as $handle) {
            $field = Craft::$app->fields->getFieldByHandle($handle);
            if ($field) {
                $commonTextFields[] = [
                    'label' => $field->name,
                    'value' => $field->handle,
                ];
            }
        }

        return $commonTextFields;
    }

    public function getFieldOfTypeFromEntry(Entry $entry, string $fieldClass): ?FieldInterface
    {
        $layout = $entry->getFieldLayout();

        if (!$layout) {
            return null;
        }

        $fields = $layout->getCustomFields();
        foreach ($fields as $field) {
            if ($field instanceof $fieldClass) {
                return $field;
            }
        }

        return null;
    }

    public static function isMarkdownCreationEnabled(): bool
    {
        return Llmify::getInstance()->getSettings()->isEnabled;
    }

    /**
     * Get combined dropdown options for front matter field selection.
     * Returns built-in fields (with builtin: prefix) and entry fields (with field: prefix).
     *
     * @param Section|null $section Optional section to get entry fields from
     * @param Entry|null $entry Optional entry to get entry fields from
     * @return array<int, array{label: string, value?: string, disabled?: bool}>
     */
    public function getFrontMatterFieldOptions(?Section $section = null, ?Entry $entry = null): array
    {
        $options = [
            ['label' => '--- Built-in Fields ---', 'disabled' => true],
            ['label' => 'Title', 'value' => 'builtin:title'],
            ['label' => 'Description', 'value' => 'builtin:description'],
            ['label' => 'URL', 'value' => 'builtin:url'],
            ['label' => 'URI', 'value' => 'builtin:uri'],
            ['label' => 'Date Modified', 'value' => 'builtin:date_modified'],
            ['label' => 'Date Created', 'value' => 'builtin:date_created'],
            ['label' => 'Section', 'value' => 'builtin:section'],
            ['label' => 'Entry Type', 'value' => 'builtin:entry_type'],
            ['label' => 'Author', 'value' => 'builtin:author'],
        ];

        // Get entry type fields (without "Custom Text" option)
        $entryTypes = [];
        if ($entry !== null) {
            $entryTypes = [$entry->type];
        } elseif ($section !== null) {
            $entryTypes = $section->getEntryTypes();
        }

        if (!empty($entryTypes)) {
            $textFields = $this->getCommonTextFieldsForEntryTypes($entryTypes);
            $contentBlockFields = $this->getTextFieldsInContentBlocks($entryTypes);

            if (!empty($textFields)) {
                $options[] = ['label' => '--- Entry Fields ---', 'disabled' => true];
                foreach ($textFields as $field) {
                    $options[] = [
                        'label' => $field['label'],
                        'value' => 'field:' . $field['value'],
                    ];
                }
            }

            if (!empty($contentBlockFields)) {
                $options[] = ['label' => '--- Content Blocks ---', 'disabled' => true];
                foreach ($contentBlockFields as $field) {
                    $options[] = [
                        'label' => $field['label'],
                        'value' => 'field:' . $field['value'],
                    ];
                }
            }
        }

        return $options;
    }
}
