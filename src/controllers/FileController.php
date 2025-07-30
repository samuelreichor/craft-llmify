<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use yii\base\Exception;
use yii\web\Response;

class FileController extends Controller
{
    public function actionGenerateLlmsTxt(): Response
    {
        $fileContent = Llmify::getInstance()->llms->getLlmsTxtContent();

        Craft::$app->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($fileContent);
    }

    /**
     * @throws Exception
     */
    public function actionGenerateLlmsFullTxt(): Response
    {
        $fileContent = Llmify::getInstance()->llms->getLlmsFullContent();

        Craft::$app->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($fileContent);
    }

    /**
     * @throws SiteNotFoundException
     */
    public function actionGeneratePageMd(string $slug): Response
    {
        $uri = preg_replace('/\.md$/', '', $slug);
        $fileContent = Llmify::getInstance()->llms->getMarkdownForUri($uri);

        Craft::$app->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($fileContent);
    }
}
