<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\helpers\App;
use craft\web\Controller;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\enums\LlmRequestType;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\LlmsService;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
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
     *
     * @throws ForbiddenHttpException
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireApiAccess();

        Craft::$app->getResponse()->getHeaders()->set('X-Robots-Tag', 'noindex, nofollow');

        return true;
    }

    /**
     * Guards the headless API. The endpoints are only available when headless
     * mode is enabled. If an API token is configured (in the plugin settings or
     * via an environment variable), requests must send it in the
     * `X-Llmify-Token` header; if no token is configured the endpoints are
     * reachable without one.
     *
     * @throws ForbiddenHttpException
     */
    private function requireApiAccess(): void
    {
        if (!Llmify::getInstance()->getSettings()->headlessMode) {
            throw new ForbiddenHttpException('The LLMify API is only available in headless mode.');
        }

        $token = trim((string)App::parseEnv(Llmify::getInstance()->getSettings()->apiToken));

        // No token configured — the endpoints are unprotected.
        if ($token === '') {
            return;
        }

        $provided = (string)$this->request->getHeaders()->get(Constants::HEADER_API_TOKEN, '');

        if (!hash_equals($token, $provided)) {
            throw new ForbiddenHttpException('Invalid or missing LLMify API token.');
        }
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
     * Returns the stored markdown for a single page, identified by its `uri`
     * within the requested site. Includes front matter and only serves pages
     * whose section is enabled — letting a headless front end serve the
     * pre-generated `.md` files without re-converting on every request.
     *
     * @throws SiteNotFoundException
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws InvalidConfigException
     */
    public function actionPage(): Response
    {
        $this->resolveSite();

        $uri = (string)$this->request->getRequiredParam('uri');
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $markdown = Llmify::getInstance()->markdown->getRenderedMarkdown($uri, $siteId);

        return $this->respondWithMarkdown($markdown ?? '', "Markdown for {$uri}");
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
