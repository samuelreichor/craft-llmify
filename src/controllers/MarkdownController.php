<?php

namespace samuelreichor\llmify\controllers;

use craft\helpers\Queue;
use craft\web\Controller;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\jobs\GenerateMarkdownJob;
use samuelreichor\llmify\services\HelperService;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class MarkdownController extends Controller
{
    /**
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionGenerate(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Constants::PERMISSION_GENERATE);

        Queue::push(new GenerateMarkdownJob());
        $this->setSuccessFlash('Markdown generation started. A job has been added to the queue.');

        return $this->redirectToPostedUrl();
    }

    /**
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionClear(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Constants::PERMISSION_CLEAR);

        HelperService::invalidateCaches();
        $this->setSuccessFlash('Markdown caches cleared.');

        return $this->redirectToPostedUrl();
    }
}
