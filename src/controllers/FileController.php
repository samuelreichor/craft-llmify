<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use yii\web\Response;

class FileController extends Controller
{
    public function actionGenerateLlmsTxt(): Response
    {
        $fileContent = Llmify::getInstance()->llms->getMarkdown();

        Craft::$app->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($fileContent);
    }
    public function actionGenerateLlmsFullTxt(): Response
    {
        $fileContent = "Generiert am: " . date('Y-m-d H:i:s');

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
