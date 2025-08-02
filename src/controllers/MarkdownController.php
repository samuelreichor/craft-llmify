<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use craft\helpers\Queue;
use craft\queue\jobs\ResaveElements;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use yii\console\ExitCode;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class MarkdownController extends Controller
{
    /**
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function actionGenerate(): Response
    {
        $this->requirePostRequest();

        Llmify::getInstance()->refresh->refreshAll();
        
        return $this->redirectToPostedUrl();
    }

    /**
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     */
    public function actionClear(): Response
    {
        $this->requirePostRequest();

        try {
            Craft::$app->getDb()->createCommand()->truncateTable(Constants::TABLE_PAGES)->execute();
        } catch (\Exception $e) {
            Craft::error('Could not clear markdowns' . $e->getMessage(), __METHOD__);
        }

        return $this->redirectToPostedUrl();
    }
}
