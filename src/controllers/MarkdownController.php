<?php

namespace samuelreichor\llmify\controllers;

use craft\web\Controller;
use samuelreichor\llmify\Constants;
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
    public function actionClearCaches(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Constants::PERMISSION_CLEAR);

        HelperService::invalidateCaches();
        $this->setSuccessFlash('Markdown caches cleared.');

        return $this->redirectToPostedUrl();
    }
}
