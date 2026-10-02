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
        $this->setSuccessFlash('Cache warming started. A job has been added to the queue.');

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
        $this->setSuccessFlash('Markdown cache cleared.');

        return $this->redirectToPostedUrl();
    }

    /**
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
            $reason = $markdownService->getPageFailure($element)['reason'] ?? 'Check your template setup and the LLMify log.';
            return $this->asFailure("Markdown could not be cached. {$reason}");
        }

        return $this->asSuccess('Markdown cached.');
    }

    /**
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
        $markdownService = Llmify::getInstance()->markdown;
        Craft::$app->getCache()->delete($markdownService->getPageCacheKey($element));
        Craft::$app->getCache()->delete($markdownService->getFailureCacheKey($element));

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
