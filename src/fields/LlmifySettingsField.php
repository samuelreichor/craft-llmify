<?php

namespace samuelreichor\llmify\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Entry;
use craft\helpers\Json;
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
        $view = Craft::$app->getView();
        $textFieldOptions = [];
        $defaultValues = [
            'llmTitleSource' => 'custom',
            'llmDescriptionSource' => 'custom',
        ];

        if (get_class($element) === Entry::class) {
            $textFieldOptions = Llmify::getInstance()->helper->getTextFieldsForEntry($element);
            $metaDataService = new MetaDataService($element);
            $defaultValues['llmTitleSource'] = $metaDataService->getContentTitleSource();
            $defaultValues['llmDescriptionSource'] = $metaDataService->getContentDescriptionSource();
        }

        return $view->renderTemplate('llmify/fields/llmify-settings-field/input', [
            'name' => $this->handle,
            'value' => $value,
            'textFieldOptions' => $textFieldOptions,
            'field' => $this,
            'defaultValues' => $defaultValues,
        ]);
    }
}
