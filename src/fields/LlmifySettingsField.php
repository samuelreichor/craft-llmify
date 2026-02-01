<?php

namespace samuelreichor\llmify\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\MetadataService;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\db\Exception;
use yii\db\Schema;

class LlmifySettingsField extends Field
{
    public static function displayName(): string
    {
        return 'LLMify Settings';
    }

    public function getContentColumnType(): string
    {
        return Schema::TYPE_TEXT;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }
        return $value;
    }

    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return parent::serializeValue($value, $element);
    }

    /**
     * @throws Exception
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws \yii\base\Exception
     * @throws LoaderError
     */
    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        if ($element::class !== Entry::class) {
            return 'Only available for Entries.';
        }

        $view = Craft::$app->getView();
        $helperService = Llmify::getInstance()->helper;
        $textFieldOptions = $helperService->getTextFieldsForEntry($element);
        $frontMatterFieldOptions = $helperService->getFrontMatterFieldOptions(null, $element);
        $metaDataService = new MetaDataService($element);
        $defaultValues['llmTitleSource'] = $metaDataService->getContentTitleSource();
        $defaultValues['llmDescriptionSource'] = $metaDataService->getContentDescriptionSource();

        // Determine if override is active for each section (existing values or explicit flag)
        $isOverrideTitleActive = !empty($value['llmTitleSource'])
            || ($value['overrideTitleSettings'] ?? false);

        $isOverrideDescriptionActive = !empty($value['llmDescriptionSource'])
            || ($value['overrideDescriptionSettings'] ?? false);

        $isOverrideFrontMatterActive = ($value['overrideFrontMatterSettings'] ?? false);

        // Get default front matter fields from site settings
        $siteId = $element->siteId;
        $globalSettings = Llmify::getInstance()->settings->getGlobalSetting($siteId);
        $defaultFrontMatterFields = $globalSettings->frontMatterFields;

        // Check if section has override
        $sectionId = $element->sectionId;
        $contentSettings = Llmify::getInstance()->settings->getContentSetting($sectionId, $siteId);
        if ($contentSettings->overrideFrontMatter && !empty($contentSettings->frontMatterFields)) {
            $defaultFrontMatterFields = $contentSettings->frontMatterFields;
        }

        // Build section settings URL
        $sectionSettingsUrl = UrlHelper::cpUrl("llmify/content/{$sectionId}");

        return $view->renderTemplate('llmify/fields/llmify-settings-field/input', [
            'name' => $this->handle,
            'value' => $value,
            'textFieldOptions' => $textFieldOptions,
            'frontMatterFieldOptions' => $frontMatterFieldOptions,
            'field' => $this,
            'defaultValues' => $defaultValues,
            'isOverrideTitleActive' => $isOverrideTitleActive,
            'isOverrideDescriptionActive' => $isOverrideDescriptionActive,
            'isOverrideFrontMatterActive' => $isOverrideFrontMatterActive,
            'defaultFrontMatterFields' => $defaultFrontMatterFields,
            'sectionSettingsUrl' => $sectionSettingsUrl,
        ]);
    }
}
