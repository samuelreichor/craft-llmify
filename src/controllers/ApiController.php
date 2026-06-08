<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use samuelreichor\llmify\enums\LlmRequestType;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\LlmsService;
use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
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
    public $enableCsrfValidation = false;

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
     * Fetches a front-end URL and returns its converted markdown without
     * persisting it. Lets a headless front end render individual `.md` pages
     * on demand.
     *
     * The target URL must belong to one of the configured site Base URL hosts
     * (SSRF guard), so this cannot be used to fetch arbitrary internal hosts.
     *
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws Throwable
     */
    public function actionConvert(): Response
    {
        $this->requirePostRequest();

        $url = (string)$this->request->getRequiredBodyParam('url');
        $this->assertAllowedHost($url);

        $markdown = Llmify::getInstance()->request->fetchAndConvert($url);

        if ($markdown === null) {
            throw new NotFoundHttpException("URL could not be fetched: {$url}");
        }

        Craft::$app->getResponse()->getHeaders()->set('Content-Type', 'text/markdown; charset=UTF-8');

        return $this->asRaw($markdown);
    }

    /**
     * Ensures the given URL uses http(s) and targets a host that belongs to one
     * of the configured site Base URLs. Prevents the convert endpoint from being
     * used as a server-side request forgery vector.
     *
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    private function assertAllowedHost(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '') {
            throw new BadRequestHttpException('A valid http(s) URL is required.');
        }

        $allowedHosts = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $baseUrl = $site->getBaseUrl();
            if ($baseUrl === null) {
                continue;
            }

            $baseHost = parse_url($baseUrl, PHP_URL_HOST);
            if (is_string($baseHost) && $baseHost !== '') {
                $allowedHosts[] = strtolower($baseHost);
            }
        }

        if (!in_array(strtolower($host), $allowedHosts, true)) {
            throw new ForbiddenHttpException("URL host is not an allowed site host: {$host}");
        }
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
