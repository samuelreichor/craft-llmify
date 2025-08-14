<?php

namespace samuelreichor\llmify\utilities;

use Craft;
use craft\base\Utility;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;

/**
 * LlMify utility
 */
class Utils extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('llmify', 'LLMify');
    }

    public static function id(): string
    {
        return 'llmify';
    }

    public static function icon(): ?string
    {
        return 'wrench';
    }

    /**
     * @throws SyntaxError
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('llmify/utilities/actions.twig');
    }
}
