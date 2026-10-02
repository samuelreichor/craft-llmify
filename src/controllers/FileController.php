<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\base\Element;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use samuelreichor\llmify\enums\LlmRequestType;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\HelperService;
use yii\base\Exception;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FileController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Craft::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        Craft::$app->response->headers->set('Vary', 'Accept');

        return true;
    }

    /**
     * @throws Exception
     */
    public function actionGenerateLlmsTxt(): Response
    {
        $fileContent = Llmify::getInstance()->llms->getLlmsTxtContent();
        if (!$fileContent) {
            Craft::error(
                'The `llms.txt` file could not be found. Please review the plugin settings to ensure that at least one site is enabled and that content is available to be displayed.',
                'llmify'
            );
            throw new NotFoundHttpException('llms.txt file not found.');
        }

        Craft::$app->response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');

        Llmify::getInstance()->fireLlmRequest(LlmRequestType::Direct);

        return $this->asRaw($fileContent);
    }

    /**
     * @throws Exception
     */
    public function actionGenerateLlmsFullTxt(): Response
    {
        $fileContent = Llmify::getInstance()->llms->getLlmsFullContent();

        if (!$fileContent) {
            Craft::error(
                'The `llms-full.txt` file could not be found. Make sure it is enabled for this site and generated with `php craft llmify/llms-full/generate`, e.g. by a cron job.',
                'llmify'
            );
            throw new NotFoundHttpException('llms-full.txt file not found.');
        }

        Craft::$app->response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');

        Llmify::getInstance()->fireLlmRequest(LlmRequestType::Direct);

        return $this->asRaw($fileContent);
    }

    /**
     * Serves the markdown of the page at `{uri}.md`.
     *
     * @throws SiteNotFoundException
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionGeneratePageMd(string $slug): Response
    {
        $uri = preg_replace('/\.md$/', '', $slug);
        if ($uri === 'index') {
            $uri = Element::HOMEPAGE_URI;
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $markdownService = Llmify::getInstance()->markdown;
        $element = $markdownService->findElementByUri($uri, $siteId);
        $fileContent = $element ? $markdownService->getPageMarkdown($element) : '';

        if (!$fileContent) {
            throw new NotFoundHttpException('Markdown not found for URI: ' . $uri);
        }

        $canonicalUrl = UrlHelper::siteUrl($uri === Element::HOMEPAGE_URI ? '' : $uri);
        $linkHeader = ['<' . $canonicalUrl . '>; rel="canonical"'];
        $llmsTxtUrl = HelperService::getLlmsTxtUrl($siteId);
        if ($llmsTxtUrl) {
            $linkHeader[] = '<' . $llmsTxtUrl . '>; rel="describedby"';
        }

        Craft::$app->response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');
        Craft::$app->response->headers->set('Link', implode(', ', $linkHeader));

        Llmify::getInstance()->fireLlmRequest(
            LlmRequestType::Direct,
            elementId: $element->id,
            elementType: get_class($element),
            url: $element->getUrl(),
        );

        return $this->asRaw($fileContent);
    }

    /**
     * Serves the markdown of the requested page to AI bots and clients asking
     * for `text/markdown` (auto-serve routes them here). Pages without
     * markdown are rendered as usual.
     *
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionNegotiatedMd(): Response
    {
        $element = Craft::$app->getUrlManager()->getMatchedElement();

        if (!$element) {
            throw new NotFoundHttpException();
        }

        $markdownService = Llmify::getInstance()->markdown;
        $fileContent = $markdownService->getPageMarkdown($element);
        $headers = Craft::$app->response->headers;

        if (!$fileContent) {
            $headers->remove('X-Robots-Tag');
            $headers->remove('Vary');
            $route = $markdownService->getOriginalRoute($element);

            if ($route === null) {
                throw new NotFoundHttpException();
            }

            return is_array($route)
                ? Craft::$app->runAction($route[0], $route[1] ?? [])
                : Craft::$app->runAction($route);
        }

        $linkHeader = ['<' . HelperService::getMarkdownUrl($element->uri, $element->siteId) . '>; rel="alternate"; type="text/markdown"'];
        $llmsTxtUrl = HelperService::getLlmsTxtUrl($element->siteId);
        if ($llmsTxtUrl) {
            $linkHeader[] = '<' . $llmsTxtUrl . '>; rel="describedby"';
        }

        $headers->set('Content-Type', 'text/markdown; charset=UTF-8');
        $headers->set('Vary', 'Accept, User-Agent');
        $headers->set('Link', implode(', ', $linkHeader));

        Llmify::getInstance()->fireLlmRequest(
            LlmRequestType::Negotiated,
            elementId: $element->id,
            elementType: get_class($element),
            uri: $element->uri,
        );

        return $this->asRaw($fileContent);
    }
}
