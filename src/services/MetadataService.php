<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\base\FieldInterface;
use craft\elements\ContentBlock;
use craft\elements\Entry;
use craft\errors\InvalidFieldException;
use samuelreichor\llmify\fields\LlmifySettingsField;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\ContentSettings;
use yii\db\Exception;

class MetadataService extends Component
{
    public ?ContentSettings $metaContent;
    public Entry $entry;
    public ?FieldInterface $entrySettingsField;

    /**
     * @throws Exception
     */
    public function __construct(Entry $entry)
    {
        parent::__construct();
        $this->entry = $entry;
        $this->metaContent = Llmify::getInstance()->settings->getContentSetting($entry->sectionId, $entry->siteId);
        $this->entrySettingsField = Llmify::getInstance()->helper->getFieldOfTypeFromEntry($this->entry, LlmifySettingsField::class);
    }

    public function getContentTitle(): string
    {
        return $this->metaContent->llmTitle;
    }

    public function getContentTitleSource(): string
    {
        return $this->metaContent->llmTitleSource;
    }

    public function getContentDescription(): string
    {
        return $this->metaContent->llmDescription;
    }

    public function getContentDescriptionSource(): string
    {
        return $this->metaContent->llmDescriptionSource;
    }

    public function getMetaContentId(): int
    {
        return $this->metaContent->id;
    }

    /**
     * @throws InvalidFieldException
     */
    public function getLlmTitle(): string
    {
        $sourceHandle = null;
        $customValue = null;

        if ($this->entrySettingsField) {
            $fieldData = $this->entry->getFieldValue($this->entrySettingsField->handle);

            // Only use entry-level data if override is explicitly enabled
            if ($fieldData['overrideTitleSettings'] ?? false) {
                $sourceHandle = $fieldData['llmTitleSource'] ?? null;
                $customValue = $fieldData['llmTitle'] ?? null;
            }
        }

        if ($sourceHandle === null || $sourceHandle === '') {
            $sourceHandle = $this->getContentTitleSource();
            $customValue = $this->getContentTitle();
        }

        if ($sourceHandle === 'custom') {
            return $customValue ?? '';
        }

        if ($sourceHandle) {
            return $this->resolveFieldValue($sourceHandle);
        }

        return $this->entry->title ?? '';
    }

    /**
     * @throws InvalidFieldException
     */
    public function getLlmDescription(): string
    {
        $sourceHandle = null;
        $customValue = null;

        if ($this->entrySettingsField) {
            $fieldData = $this->entry->getFieldValue($this->entrySettingsField->handle);

            // Only use entry-level data if override is explicitly enabled
            if ($fieldData['overrideDescriptionSettings'] ?? false) {
                $sourceHandle = $fieldData['llmDescriptionSource'] ?? null;
                $customValue = $fieldData['llmDescription'] ?? null;
            }
        }

        if ($sourceHandle === null || $sourceHandle === '') {
            $sourceHandle = $this->getContentDescriptionSource();
            $customValue = $this->getContentDescription();
        }

        if ($sourceHandle === 'custom') {
            return $customValue ?? '';
        }

        if ($sourceHandle) {
            return $this->resolveFieldValue($sourceHandle);
        }

        return '';
    }

    /**
     * Resolves a field value from either a direct field or a Content Block nested field.
     * Supports dot notation for Content Block fields (e.g., "contentBlockHandle.fieldHandle").
     */
    private function resolveFieldValue(string $sourceHandle): string
    {
        if (str_contains($sourceHandle, '.')) {
            [$contentBlockHandle, $fieldHandle] = explode('.', $sourceHandle, 2);
            $contentBlock = $this->entry->getFieldValue($contentBlockHandle);

            if ($contentBlock instanceof ContentBlock) {
                $value = $contentBlock->getFieldValue($fieldHandle);
                return strip_tags((string)($value ?? ''));
            }

            return '';
        }

        return strip_tags((string)($this->entry[$sourceHandle] ?? ''));
    }
}
