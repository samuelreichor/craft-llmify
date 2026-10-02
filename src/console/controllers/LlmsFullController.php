<?php

namespace samuelreichor\llmify\console\controllers;

use Craft;
use craft\console\Controller;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\HelperService;
use samuelreichor\llmify\services\LlmsService;
use yii\console\ExitCode;

class LlmsFullController extends Controller
{
    public $defaultAction = 'generate';

    /**
     * @throws \yii\base\Exception
     */
    public function actionGenerate(): int
    {
        if (!HelperService::isMarkdownCreationEnabled()) {
            $this->stderr("LLMify is disabled in the plugin settings.\n");
            return ExitCode::UNAVAILABLE;
        }

        $sites = Craft::$app->getSites();

        foreach (Llmify::getInstance()->settings->getAllActiveGlobalSettingsIds() as $siteId) {
            $site = $sites->getSiteById($siteId);

            if (!$site) {
                continue;
            }

            $sites->setCurrentSite($site);
            $pageCount = (new LlmsService())->generateLlmsFullContent();

            if ($pageCount === null) {
                $this->stdout("{$site->name}: llms-full.txt is disabled, skipped.\n");
                continue;
            }

            $this->stdout("{$site->name}: generated llms-full.txt with {$pageCount} pages.\n");
        }

        return ExitCode::OK;
    }
}
