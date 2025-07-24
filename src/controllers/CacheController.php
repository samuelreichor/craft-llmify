<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\web\Controller;
use samuelreichor\llmify\services\LlmifyService;
use yii\caching\TagDependency;
use yii\web\Response;

class CacheController extends Controller
{
    public function actionClear(): Response
    {
        TagDependency::invalidate(Craft::$app->getCache(), LlmifyService::CACHE_TAG);
        Craft::$app->getSession()->setNotice('Llmify cache cleared.');
        return $this->redirectToPostedUrl();
    }
}
