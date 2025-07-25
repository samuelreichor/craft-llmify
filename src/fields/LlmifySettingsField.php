<?php

namespace samuelreichor\llmify\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Json;
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

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        $view = Craft::$app->getView();
        $id = $view->formatInputId($this->handle);
        $namespacedId = $view->namespaceInputId($id);

        return $view->renderTemplate('llmify/fields/llmify-settings-field/input', [
            'id' => $id,
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
        ]);
    }
}
