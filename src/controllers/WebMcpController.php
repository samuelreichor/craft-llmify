<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\HelperService;
use samuelreichor\llmify\services\WebMcpService;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * WebMCP Controller
 *
 * Public, read-only endpoints that back the browser WebMCP tools:
 *
 *  - `actionScript()`   serves `/webmcp.js`, the script that registers the tools.
 *  - `actionSearch()`   the `search_content` tool backend.
 *  - `actionPage()`     the `get_page` tool backend.
 *  - `actionSections()` the `list_sections` tool backend.
 *
 * The endpoints are unauthenticated by design (the agent runs in the visitor's
 * browser), so they only ever expose content that is already public via the
 * markdown routes. All actions are GET/idempotent, the search query is length-
 * and limit-capped and cached, and the whole controller 404s unless the plugin
 * is enabled and WebMCP support is turned on in the plugin settings.
 *
 * @author Samuel Reichör <samuelreichor@gmail.com>
 */
class WebMcpController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @inheritdoc
     *
     * @throws NotFoundHttpException
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Craft routes `actions/llmify/web-mcp/<action>` straight to this
        // controller regardless of the site URL rules, so the master toggle has
        // to be re-checked here — the URL-rule guard in Llmify::init() only
        // covers the pretty `/webmcp.js` route.
        if (!HelperService::isMarkdownCreationEnabled() || !Llmify::getInstance()->getSettings()->enableWebMcp) {
            throw new NotFoundHttpException('WebMCP support is not enabled.');
        }

        Craft::$app->getResponse()->getHeaders()->set('X-Robots-Tag', 'noindex, nofollow');

        return true;
    }

    /**
     * Serves the client-side script that registers the WebMCP tools.
     */
    public function actionScript(): Response
    {
        $script = Llmify::getInstance()->webMcp->getScript();

        $headers = Craft::$app->getResponse()->getHeaders();
        $headers->set('Content-Type', 'text/javascript; charset=UTF-8');
        // The tool set changes with settings/content, so always revalidate
        // rather than letting the browser serve a stale registration script.
        $headers->set('Cache-Control', 'no-cache');

        return $this->asRaw($script);
    }

    /**
     * Backend for the `search_content` tool. Enforces a minimum query length and
     * caps the result count; responses are briefly cached to blunt abuse of the
     * unauthenticated endpoint.
     *
     * @throws BadRequestHttpException
     * @throws SiteNotFoundException
     */
    public function actionSearch(): Response
    {
        $query = trim((string)$this->request->getRequiredParam('q'));
        $queryLength = mb_strlen($query);

        if ($queryLength < WebMcpService::MIN_QUERY_LENGTH) {
            throw new BadRequestHttpException('The search query must be at least ' . WebMcpService::MIN_QUERY_LENGTH . ' characters long.');
        }

        // Cap the length so the unauthenticated endpoint can't be driven with
        // huge unique queries that each miss the cache and hit the search index.
        if ($queryLength > WebMcpService::MAX_QUERY_LENGTH) {
            throw new BadRequestHttpException('The search query must be at most ' . WebMcpService::MAX_QUERY_LENGTH . ' characters long.');
        }

        // Clamp before building the cache key so out-of-range limits can't
        // flood the cache with duplicate entries.
        $limit = (int)$this->request->getParam('limit', WebMcpService::DEFAULT_SEARCH_LIMIT);
        $limit = max(1, min($limit, WebMcpService::MAX_SEARCH_LIMIT));
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        $cacheKey = ['llmify-webmcp-search', $siteId, mb_strtolower($query), $limit];
        $results = Craft::$app->getCache()->getOrSet($cacheKey, function() use ($query, $siteId, $limit) {
            return Llmify::getInstance()->webMcp->search($query, $siteId, $limit);
        }, 60);

        return $this->asJson(['results' => $results]);
    }

    /**
     * Backend for the `get_page` tool. Delegates to `LlmsService` so the same
     * exclusion gating as the public `.md` routes applies. On-demand generation
     * is disabled here and the lookup is cached, so this unauthenticated
     * endpoint can only ever serve already-generated markdown and can't be used
     * to trigger blocking server-side renders.
     *
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws SiteNotFoundException
     */
    public function actionPage(): Response
    {
        $uri = (string)$this->request->getRequiredParam('uri');
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        $cacheKey = ['llmify-webmcp-page', $siteId, $uri];
        $markdown = Craft::$app->getCache()->getOrSet($cacheKey, function() use ($uri) {
            return Llmify::getInstance()->llms->getMarkdownForUri($uri, allowOnDemand: false);
        }, 60);

        if ($markdown === '') {
            throw new NotFoundHttpException('No content found for URI: ' . $uri);
        }

        return $this->asJson(['uri' => $uri, 'markdown' => $markdown]);
    }

    /**
     * Backend for the `list_sections` tool.
     *
     * @throws SiteNotFoundException
     */
    public function actionSections(): Response
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $sections = Llmify::getInstance()->webMcp->getSections($siteId);

        return $this->asJson(['sections' => $sections]);
    }
}
