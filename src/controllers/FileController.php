<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use yii\base\Exception;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FileController extends Controller
{
    protected array|bool|int $allowAnonymous = true;
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
                'The `llms-full.txt` file could not be found. Please review the plugin settings to ensure that at least one site is enabled and that content is available to be displayed.',
                'llmify'
            );
            throw new NotFoundHttpException('llms-full.txt file not found.');
        }

        Craft::$app->response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');

        return $this->asRaw($fileContent);
    }

    /**
     * @throws SiteNotFoundException
     * @throws \yii\db\Exception
     * @throws NotFoundHttpException
     */
    public function actionGeneratePageMd(string $slug): Response
    {
        $uri = preg_replace('/\.md$/', '', $slug);
        $fileContent = Llmify::getInstance()->llms->getMarkdownForUri($uri);

        if (!$fileContent) {
            throw new NotFoundHttpException('Markdown not found for URI: ' . $uri);
        }

        Craft::$app->response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');

        return $this->asRaw($fileContent);
    }
}
