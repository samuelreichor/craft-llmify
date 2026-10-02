<?php

namespace samuelreichor\llmify\console\controllers;

use craft\console\Controller;
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

        foreach (LlmsService::generateLlmsFullForAllSites() as $siteName => $pageCount) {
            if ($pageCount === null) {
                $this->stdout("{$siteName}: llms-full.txt is disabled, skipped.\n");
                continue;
            }

            $this->stdout("{$siteName}: generated llms-full.txt with {$pageCount} pages.\n");
        }

        return ExitCode::OK;
    }
}
