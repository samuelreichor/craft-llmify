<?php

namespace samuelreichor\llmify\console\controllers;

use Craft;
use craft\console\Controller;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\HelperService;
use yii\console\ExitCode;

/**
 * Manages the markdown caches.
 */
class MarkdownController extends Controller
{
    public $defaultAction = 'generate';

    /**
     * llmify/markdown/generate — caches the markdown of every page that is
     * not cached yet. Run it on a schedule (e.g. nightly) to keep the caches
     * warm on sites under heavy load.
     *
     * @throws \yii\base\Exception
     */
    public function actionGenerate(): int
    {
        $markdownService = Llmify::getInstance()->markdown;

        foreach (Llmify::getInstance()->settings->getAllActiveGlobalSettingsIds() as $siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);

            if (!$site) {
                continue;
            }

            $elements = $markdownService->getServableElements($siteId);
            $generated = $markdownService->generate($elements);
            $this->stdout("{$site->name}: {$generated} of " . count($elements) . " pages cached.\n");
        }

        return ExitCode::OK;
    }

    /**
     * llmify/markdown/clear — clears all markdown caches.
     */
    public function actionClear(): int
    {
        HelperService::invalidateCaches();
        $this->stdout("Markdown caches cleared.\n");

        return ExitCode::OK;
    }
}
