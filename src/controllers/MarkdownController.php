<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Queue;
use craft\web\Controller;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\jobs\GenerateMarkdownJob;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\HelperService;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
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

    /**
     * Rebuilds the cached markdown of a single page.
     *
     * @throws MethodNotAllowedHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws Throwable
     */
    public function actionGeneratePage(int $elementId, int $siteId): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Constants::PERMISSION_GENERATE);

        $element = $this->getElement($elementId, $siteId);
        $markdownService = Llmify::getInstance()->markdown;
        Craft::$app->getCache()->delete($markdownService->getPageCacheKey($element));

        if ($markdownService->generate([$element]) === 0) {
            return $this->asFailure('The markdown could not be generated. Check the LLMify log for details.');
        }

        return $this->asSuccess('Markdown generated.');
    }

    /**
     * Clears the cached markdown of a single page.
     *
     * @throws MethodNotAllowedHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws Throwable
     */
    public function actionClearPage(int $elementId, int $siteId): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Constants::PERMISSION_CLEAR);

        $element = $this->getElement($elementId, $siteId);
        Craft::$app->getCache()->delete(Llmify::getInstance()->markdown->getPageCacheKey($element));

        return $this->asSuccess('Markdown cache cleared.');
    }

    /**
     * @throws NotFoundHttpException
     */
    private function getElement(int $elementId, int $siteId): ElementInterface
    {
        $element = Craft::$app->getElements()->getElementById($elementId, null, $siteId);

        if (!$element) {
            throw new NotFoundHttpException('Element not found.');
        }

        return $element;
    }
}
