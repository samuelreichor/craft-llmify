<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use samuelreichor\llmify\enums\LlmRequestType;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\LlmsService;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * API Controller
 *
 * Host-independent endpoints (`/actions/llmify/api/...`) for headless front
 * ends to pull generated content from Craft. Unlike the site routes, the site
 * is selected explicitly via a `site` parameter (handle or id) instead of being
 * resolved from the request host, so the endpoints work when the front end
 * calls Craft cross-domain.
 */
class ApiController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Craft::$app->getResponse()->getHeaders()->set('X-Robots-Tag', 'noindex, nofollow');

        return true;
    }

    /**
     * Returns the `llms.txt` content for the requested site.
     *
     * @throws SiteNotFoundException
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionLlmsTxt(): Response
    {
        $this->resolveSite();

        return $this->respondWithMarkdown((new LlmsService())->getLlmsTxtContent(), 'llms.txt');
    }

    /**
     * Returns the `llms-full.txt` content for the requested site.
     *
     * @throws SiteNotFoundException
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionLlmsFullTxt(): Response
    {
        $this->resolveSite();

        return $this->respondWithMarkdown((new LlmsService())->getLlmsFullContent(), 'llms-full.txt');
    }

    /**
     * Resolves the `site` parameter (handle or id) and makes it the current site
     * so `LlmsService` generates content for it. Falls back to the primary site.
     *
     * @throws BadRequestHttpException
     * @throws SiteNotFoundException
     */
    private function resolveSite(): void
    {
        $param = $this->request->getParam('site');
        $sites = Craft::$app->getSites();

        if ($param === null) {
            $sites->setCurrentSite($sites->getPrimarySite());
            return;
        }

        $site = is_numeric($param)
            ? $sites->getSiteById((int)$param)
            : $sites->getSiteByHandle((string)$param);

        if ($site === null) {
            throw new BadRequestHttpException("Unknown site: {$param}");
        }

        $sites->setCurrentSite($site);
    }

    /**
     * @throws NotFoundHttpException
     */
    private function respondWithMarkdown(string $content, string $label): Response
    {
        if ($content === '') {
            throw new NotFoundHttpException("{$label} could not be generated for this site.");
        }

        Llmify::getInstance()->fireLlmRequest(LlmRequestType::Direct);

        Craft::$app->getResponse()->getHeaders()->set('Content-Type', 'text/markdown; charset=UTF-8');

        return $this->asRaw($content);
    }
}
