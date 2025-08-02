<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\base\FieldInterface;
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
            $sourceHandle = $fieldData['llmTitleSource'] ?? null;
            $customValue = $fieldData['llmTitle'] ?? null;
        }

        if ($sourceHandle === null) {
            $sourceHandle = $this->getContentTitleSource();
            $customValue = $this->getContentTitle();
        }

        if ($sourceHandle === 'custom') {
            return $customValue ?? '';
        }

        if ($sourceHandle) {
            $value = $this->entry[$sourceHandle];
            return (string) $value;
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
            $sourceHandle = $fieldData['llmDescriptionSource'] ?? null;
            $customValue = $fieldData['llmDescription'] ?? null;
        }

        if ($sourceHandle === null) {
            $sourceHandle = $this->getContentDescriptionSource();
            $customValue = $this->getContentDescription();
        }

        if ($sourceHandle === 'custom') {
            return $customValue ?? '';
        }

        if ($sourceHandle) {
            $value = $this->entry[$sourceHandle];
            return (string) $value;
        }

        return '';
    }
}
