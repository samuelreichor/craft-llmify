<?php

namespace samuelreichor\llmify\utilities;

use Craft;
use craft\base\Utility;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\records\PageRecord;
use samuelreichor\llmify\services\PermissionService;
use Throwable;
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
     * @throws Throwable
     */
    public static function contentHtml(): string
    {
        $activeSiteIds = Llmify::getInstance()->settings->getAllActiveGlobalSettingsIds();
        $markdownTable = [];
        foreach ($activeSiteIds as $siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $pageCount = PageRecord::find()->where(['siteId' => $siteId])->count();
                $markdownTable[] = [
                    'name' => $site->name,
                    'pageCount' => $pageCount,
                ];
            }
        }
        return Craft::$app->getView()->renderTemplate('llmify/utilities/actions.twig', [
            'markdownTable' => $markdownTable,
            'canGenerate' => PermissionService::canGenerate(),
            'canClear' => PermissionService::canClear(),
        ]);
    }
}
