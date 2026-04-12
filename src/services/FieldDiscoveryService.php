<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\fields\ContentBlock;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Section;

class FieldDiscoveryService extends Component
{
    private const SEOMATIC_CLASS = 'nystudio107\seomatic\Seomatic';

    private const SOURCE_OPTION_BASE = [
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
     * Get source options for title/description dropdowns.
     *
     * @param Section|Entry|\craft\commerce\models\ProductType $context
     * @return array<int, array{label: string, value?: string, disabled?: bool}>
     */
    public function getSourceOptions(object $context): array
    {
        if ($context instanceof Entry) {
            return $this->getSourceOptionsForEntryTypes([$context->type]);
        }

        if ($context instanceof Section) {
            return $this->getSourceOptionsForEntryTypes($context->getEntryTypes());
        }

        // Commerce Product Type
        return $this->getSourceOptionsForProductType($context);
    }

    /**
     * Get options for front matter field dropdowns.
     *
     * @param \craft\commerce\models\ProductType|null $productType
     * @return array<int, array{label: string, value?: string, disabled?: bool}>
     */
    public function getFrontMatterOptions(?Section $section = null, ?Entry $entry = null, ?object $productType = null): array
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

        if ($productType !== null) {
            $fieldLayout = $productType->getFieldLayout();
            $textFields = $this->getTextFieldsFromLayout($fieldLayout);
            if (!empty($textFields)) {
                $options[] = ['label' => '--- Product Fields ---', 'disabled' => true];
                foreach ($textFields as $field) {
                    $options[] = [
                        'label' => $field['label'],
                        'value' => 'field:' . $field['value'],
                    ];
                }
            }

            return $this->appendSeoOptions($options);
        }

        $entryTypes = [];
        if ($entry !== null) {
            $entryTypes = [$entry->type];
        } elseif ($section !== null) {
            $entryTypes = $section->getEntryTypes();
        }

        if (!empty($entryTypes)) {
            $textFields = $this->getCommonTextFields($entryTypes);
            $contentBlockFields = $this->getContentBlockFields($entryTypes);

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

        return $this->appendSeoOptions($options);
    }

    /**
     * @param EntryType[] $entryTypes
     */
    private function getSourceOptionsForEntryTypes(array $entryTypes): array
    {
        $textFields = $this->getCommonTextFields($entryTypes);
        $contentBlockFields = $this->getContentBlockFields($entryTypes);

        $result = array_merge(self::SOURCE_OPTION_BASE, $textFields);

        if (!empty($contentBlockFields)) {
            $result[] = ['label' => '--- Content Blocks ---', 'disabled' => true];
            $result = array_merge($result, $contentBlockFields);
        }

        return $this->appendSeoOptions($result);
    }

    /**
     * @param \craft\commerce\models\ProductType $productType
     */
    private function getSourceOptionsForProductType(object $productType): array
    {
        $fieldLayout = $productType->getFieldLayout();
        $textFields = $this->getTextFieldsFromLayout($fieldLayout);
        $result = array_merge(self::SOURCE_OPTION_BASE, $textFields);

        return $this->appendSeoOptions($result);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function getTextFieldsFromLayout(FieldLayout $layout): array
    {
        $textFields = [];
        foreach ($layout->getCustomFieldElements() as $layoutElement) {
            $field = $layoutElement->getField();
            if ($this->isTextField($field)) {
                $textFields[] = [
                    'label' => $layoutElement->label(),
                    'value' => $layoutElement->attribute(),
                ];
            }
        }

        return $textFields;
    }

    /**
     * @param EntryType[] $entryTypes
     * @return array<int, array{label: string, value: string}>
     */
    private function getCommonTextFields(array $entryTypes): array
    {
        if (empty($entryTypes)) {
            return [];
        }

        $allFieldsByHandle = [];
        foreach ($entryTypes as $entryType) {
            $fieldsInType = [];
            foreach ($entryType->getFieldLayout()->getCustomFieldElements() as $layoutElement) {
                $field = $layoutElement->getField();
                if ($this->isTextField($field)) {
                    $handle = $layoutElement->attribute();
                    $fieldsInType[$handle] = [
                        'label' => $layoutElement->label(),
                        'value' => $handle,
                    ];
                }
            }
            $allFieldsByHandle[] = $fieldsInType;
        }

        if (count($allFieldsByHandle) === 1) {
            return array_values($allFieldsByHandle[0]);
        }

        $commonKeys = array_keys(array_intersect_key(...$allFieldsByHandle));

        $result = [];
        foreach ($commonKeys as $handle) {
            $result[] = $allFieldsByHandle[0][$handle];
        }

        return $result;
    }

    /**
     * @param EntryType[] $entryTypes
     * @return array<int, array{label: string, value: string}>
     */
    private function getContentBlockFields(array $entryTypes): array
    {
        if (empty($entryTypes) || !class_exists(ContentBlock::class)) {
            return [];
        }

        $allContentBlockFields = [];

        foreach ($entryTypes as $entryType) {
            $contentBlockFieldsInType = [];

            foreach ($entryType->getFieldLayout()->getCustomFieldElements() as $layoutElement) {
                $field = $layoutElement->getField();
                if (!$field instanceof ContentBlock) {
                    continue;
                }

                $cbHandle = $layoutElement->attribute();
                $cbLabel = $layoutElement->label();

                foreach ($field->getFieldLayout()->getCustomFieldElements() as $nestedLayoutElement) {
                    $nestedField = $nestedLayoutElement->getField();
                    if ($this->isTextField($nestedField)) {
                        $nestedHandle = $nestedLayoutElement->attribute();
                        $key = $cbHandle . '.' . $nestedHandle;
                        $contentBlockFieldsInType[$key] = [
                            'contentBlockName' => $cbLabel,
                            'fieldName' => $nestedLayoutElement->label(),
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

    private function appendSeoOptions(array $options): array
    {
        if (!class_exists(self::SEOMATIC_CLASS)) {
            return $options;
        }

        $options[] = ['label' => '--- SEOMatic Fields ---', 'disabled' => true];
        $options[] = ['label' => 'SEO Title', 'value' => 'seomatic:seoTitle'];
        $options[] = ['label' => 'SEO Description', 'value' => 'seomatic:seoDescription'];

        return $options;
    }

    /**
     * Resolve a parsed SEO value (seoTitle or seoDescription) for an element
     * using SEOmatic's container API.
     */
    public static function resolveSeoValue(ElementInterface $element, string $seoKey): string
    {
        if (!class_exists(self::SEOMATIC_CLASS)) {
            return '';
        }

        $uri = $element->uri;
        if ($uri === null) {
            return '';
        }

        /** @var \nystudio107\seomatic\Seomatic $seomatic */
        $seomatic = \nystudio107\seomatic\Seomatic::$plugin;
        // Reset preview flag so SEOmatic reloads containers for each element
        \nystudio107\seomatic\Seomatic::$previewingMetaContainers = false;
        $seomatic->metaContainers->previewMetaContainers($uri, (int)$element->siteId, true, true, $element);
        $seomatic->metaContainers->parseGlobalVars();

        $metaGlobalVars = \nystudio107\seomatic\Seomatic::$seomaticVariable?->meta;
        if ($metaGlobalVars === null) {
            return '';
        }

        return strip_tags($metaGlobalVars->parsedValue($seoKey) ?? '');
    }

    private function isTextField(mixed $field): bool
    {
        $isCkEditorField = class_exists('\craft\ckeditor\Field') && is_a($field, '\craft\ckeditor\Field');

        return $field instanceof PlainText || $isCkEditorField;
    }
}
