<?php

namespace samuelreichor\llmify\console\controllers;

use Craft;
use craft\console\Controller;
use samuelreichor\llmify\Llmify;
use yii\console\ExitCode;
use yii\db\Exception;

/**
 * Markdown controller
 */
class MarkdownController extends Controller
{
    public $defaultAction = 'generate';

    /**
     * llmify/markdown or llmify/markdown/generate command
     *
     * @throws Exception
     * @throws \yii\base\Exception
     */
    public function actionGenerate(): int
    {
        Llmify::getInstance()->refresh->refreshAll();
        $this->stdout('Markdown generation started. Jobs have been added to the queue.' . PHP_EOL);
        return ExitCode::OK;
    }

    /**
     * llmify/markdown/clear command
     *
     * @throws Exception
     */
    public function actionClear(): int
    {
        Llmify::getInstance()->refresh->clearAll();
        $this->stdout('All markdowns successfully removed.' . PHP_EOL);
        return ExitCode::OK;
    }
}
