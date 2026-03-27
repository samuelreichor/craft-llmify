<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use craft\fields\PlainText;
use craft\helpers\UrlHelper;
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

    /**
     * Checks if Craft Commerce is installed and enabled
     */
    public static function isCommerceInstalled(): bool
    {
        $plugin = Craft::$app->plugins->getPlugin('commerce');

        if ($plugin !== null && $plugin->isInstalled) {
            return true;
        }

        return false;
    }

    /**
     * Get the groupId for an element (sectionId for entries, productTypeId for products)
     */
    public static function getGroupIdForElement(ElementInterface $element): ?int
    {
        if ($element instanceof Entry) {
            return $element->sectionId;
        }

        if (self::isCommerceInstalled() && $element instanceof \craft\commerce\elements\Product) {
            return $element->typeId;
        }

        return null;
    }

    /**
     * Get the element type class for an element
     */
    public static function getElementTypeForElement(ElementInterface $element): string
    {
        return $element::class;
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

    /**
     * Get text field options for a product type
     *
     * @return array<int, array{label: string, value?: string, disabled?: bool}>
     */
    public function getTextFieldsForProductType(object $productType): array
    {
        $fieldLayout = $productType->getFieldLayout();
        if (!$fieldLayout) {
            return self::textFieldOptionBase;
        }

        $fields = $fieldLayout->getCustomFields();
        $textFields = [];

        foreach ($fields as $field) {
            $isCkEditorField = class_exists('\craft\ckeditor\Field') && is_a($field, '\craft\ckeditor\Field');
            if ($field instanceof PlainText || $isCkEditorField) {
                $textFields[] = [
                    'label' => $field->name,
                    'value' => $field->handle,
                ];
            }
        }

        return array_merge(self::textFieldOptionBase, $textFields);
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
     * Get text field options for any element
     */
    public function getTextFieldsForElement(ElementInterface $element): array
    {
        if ($element instanceof Entry) {
            return $this->getTextFieldsForEntry($element);
        }

        if (self::isCommerceInstalled() && $element instanceof \craft\commerce\elements\Product) {
            return $this->getTextFieldsForProductType($element->getType());
        }

        return self::textFieldOptionBase;
    }

    /**
     * @param EntryType[] $entryTypes
     * @return array<int, array{label: string, value: string}>
     */
    private function getTextFieldsInContentBlocks(array $entryTypes): array
    {
        // ContentBlock fields are not available in Craft 4
        return [];
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

    public function getFieldOfTypeFromElement(ElementInterface $element, string $fieldClass): ?FieldInterface
    {
        $layout = $element->getFieldLayout();

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

    public static function getMarkdownUrl(string $uri, ?int $siteId = null): string
    {
        $mdPrefix = Llmify::getInstance()->getSettings()->markdownUrlPrefix;
        return UrlHelper::siteUrl("{$mdPrefix}/{$uri}.md", null, null, $siteId);
    }

    /**
     * Get combined dropdown options for front matter field selection.
     * Returns built-in fields (with builtin: prefix) and element fields (with field: prefix).
     *
     * @param Section|null $section Optional section to get entry fields from
     * @param Entry|null $entry Optional entry to get entry fields from
     * @param object|null $productType Optional product type to get fields from
     * @return array<int, array{label: string, value?: string, disabled?: bool}>
     */
    public function getFrontMatterFieldOptions(?Section $section = null, ?Entry $entry = null, ?object $productType = null): array
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

        // Handle product type fields
        if ($productType !== null) {
            $fieldLayout = $productType->getFieldLayout();
            if ($fieldLayout) {
                $fields = $fieldLayout->getCustomFields();
                $textFields = [];
                foreach ($fields as $field) {
                    $isCkEditorField = class_exists('\craft\ckeditor\Field') && is_a($field, '\craft\ckeditor\Field');
                    if ($field instanceof PlainText || $isCkEditorField) {
                        $textFields[] = $field;
                    }
                }

                if (!empty($textFields)) {
                    $options[] = ['label' => '--- Product Fields ---', 'disabled' => true];
                    foreach ($textFields as $field) {
                        $options[] = [
                            'label' => $field->name,
                            'value' => 'field:' . $field->handle,
                        ];
                    }
                }
            }

            return $options;
        }

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
