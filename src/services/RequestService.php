<?php

namespace samuelreichor\llmify\services;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Pipeline\Pipeline;
use Craft;
use craft\helpers\App;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use Throwable;
use yii\base\Component;

/**
 * Request Service
 */
class RequestService extends Component
{
    /**
     * @var int The max number of concurrent requests.
     */
    public int $concurrentRequests;

    /**
     * @var int The timeout for requests in seconds.
     */
    public int $requestTimeout;
    private int $generated = 0;

    public function __construct()
    {
        parent::__construct();
        $settings = Llmify::getInstance()->getSettings();
        $this->concurrentRequests = $settings->concurrentRequests;
        $this->requestTimeout = $settings->requestTimeout;
    }

    public function generateUrl(string $url, ?int $siteId = null): bool
    {
        $client = HttpClientBuilder::buildDefault();
        $request = $this->createRequest($url, $siteId);
        $response = $client->request($request);
        $status = $response->getStatus();

        if ($status !== 200) {
            $this->logUnexpectedStatus($url, $status);
        }

        return $status === 200;
    }

    /**
     * Fetches a single URL and converts its rendered HTML body to markdown,
     * without persisting it. Used by the headless convert endpoint. Returns
     * null when the URL does not respond with a 200.
     *
     * @throws Throwable
     */
    public function fetchAndConvert(string $url): ?string
    {
        $client = HttpClientBuilder::buildDefault();
        $response = $client->request($this->createRequest($url));
        $status = $response->getStatus();

        if ($status !== 200) {
            $this->logUnexpectedStatus($url, $status);
            return null;
        }

        return Llmify::getInstance()->markdown->convertHtml($response->getBody()->buffer());
    }

    public function generateUrlsWithProgress(array $urls, callable $setProgressHandler = null): void
    {
        $this->generateWithProgress($urls, $setProgressHandler, 0, count($urls));
    }

    public function generateWithProgress(array $urls, callable $setProgressHandler, int $count, int $total): void
    {
        $client = HttpClientBuilder::buildDefault();
        $isHeadless = Llmify::getInstance()->getSettings()->headlessMode;

        $concurrentIterator = Pipeline::fromIterable($urls)
            ->concurrent($this->concurrentRequests);

        foreach ($concurrentIterator as $item) {
            $count++;
            $url = is_array($item) ? $item['url'] : $item;
            $siteId = is_array($item) ? $item['siteId'] : null;
            try {
                $request = $this->createRequest($url, $siteId);
                $response = $client->request($request);
                $status = $response->getStatus();

                if ($status === 200) {
                    // In headless mode Craft never renders the front end, so the
                    // Twig save side-effect does not run. Read the fetched body
                    // and convert it here instead.
                    if ($isHeadless && is_array($item) && $item['elementId'] !== null) {
                        $this->saveFromBody($response->getBody()->buffer(), $item['elementId'], $item['siteId']);
                    }

                    $this->generated++;
                } else {
                    $this->logUnexpectedStatus($url, $status);
                }

                if (is_callable($setProgressHandler)) {
                    $this->callProgressHandler($setProgressHandler, $count, $total);
                }
            } catch (HttpException $exception) {
                Craft::error("Failed generating URL {$url}. " . $exception->getMessage());
            } catch (Throwable $exception) {
                Craft::error("Failed converting markdown for URL {$url}. " . $exception->getMessage());
            }
        }
    }

    /**
     * Converts a fetched HTML body to markdown and stores it for the given element.
     *
     * @throws Throwable
     */
    protected function saveFromBody(string $html, int $elementId, int $siteId): void
    {
        $markdownService = Llmify::getInstance()->markdown;
        $markdownService->saveMarkdown($markdownService->convertHtml($html), $elementId, $siteId);
    }

    /**
     * Builds the internal request used to render a front-end URL. Besides the
     * refresh marker it carries what the site needs to let the request through:
     * a Craft site token so offline (`isSystemLive: false`) staging sites still
     * render, and HTTP Basic Auth credentials when the site is protected that way.
     */
    protected function createRequest(string $url, ?int $siteId = null): Request
    {
        $request = new Request($url);
        $request->setHeader(Constants::HEADER_REFRESH, '1');

        if ($siteId !== null) {
            $request->setHeader('X-Craft-Site-Token', Craft::$app->getSecurity()->hashData((string)$siteId));
        }

        $settings = Llmify::getInstance()->getSettings();
        $username = (string)App::parseEnv($settings->basicAuthUsername);
        if ($username !== '') {
            $password = (string)App::parseEnv($settings->basicAuthPassword);
            $request->setHeader('Authorization', 'Basic ' . base64_encode("{$username}:{$password}"));
        }

        $request->setTcpConnectTimeout($this->requestTimeout);
        $request->setTlsHandshakeTimeout($this->requestTimeout);
        $request->setTransferTimeout($this->requestTimeout);
        $request->setInactivityTimeout($this->requestTimeout);

        return $request;
    }

    /**
     * A non-200 response means the page was skipped. Log it so a protected or
     * offline site does not fail silently.
     */
    protected function logUnexpectedStatus(string $url, int $status): void
    {
        $hint = match ($status) {
            401 => 'The site requires HTTP Basic Auth. Set the credentials in the LLMify plugin settings.',
            503 => 'The site is offline or unavailable.',
            default => '',
        };

        Craft::warning(trim("Skipped markdown generation for {$url}: the site responded with HTTP {$status}. {$hint}"), 'llmify');
    }

    /**
     * Calls the provided progress handles.
     */
    protected function callProgressHandler(callable $setProgressHandler, int $count, int $total): void
    {
        $progressLabel = Craft::t('llmify', 'Generating {count} of {total} markdowns', [
            'count' => $count,
            'total' => $total,
        ]);

        call_user_func($setProgressHandler, $count, $total, $progressLabel);
    }
}
