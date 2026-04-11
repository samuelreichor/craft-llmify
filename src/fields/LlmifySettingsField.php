<?php

namespace samuelreichor\llmify\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\HelperService;
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

    public static function icon(): string
    {
        return '@samuelreichor/llmify/icon-mask.svg';
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
        $isEntry = $element instanceof Entry;
        $isProduct = HelperService::isCommerceInstalled() && $element instanceof \craft\commerce\elements\Product;

        if (!$isEntry && !$isProduct) {
            return 'Only available for Entries and Products.';
        }

        $view = Craft::$app->getView();
        $fieldDiscovery = Llmify::getInstance()->fieldDiscovery;
        $textFieldOptions = $fieldDiscovery->getSourceOptions($isEntry ? $element : $element->getType());

        if ($isEntry) {
            $frontMatterFieldOptions = $fieldDiscovery->getFrontMatterOptions(null, $element);
        } else {
            $frontMatterFieldOptions = $fieldDiscovery->getFrontMatterOptions(null, null, $element->getType());
        }

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

        // Check if group has override
        $groupId = HelperService::getGroupIdForElement($element);
        $elementType = HelperService::getElementTypeForElement($element);
        $contentSettings = Llmify::getInstance()->settings->getContentSetting($groupId, $siteId, $elementType);
        if ($contentSettings->overrideFrontMatter && !empty($contentSettings->frontMatterFields)) {
            $defaultFrontMatterFields = $contentSettings->frontMatterFields;
        }

        // Build group settings URL
        $groupSettingsUrl = UrlHelper::cpUrl("llmify/content/{$groupId}", ['elementType' => $elementType]);

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
            'sectionSettingsUrl' => $groupSettingsUrl,
        ]);
    }
}
