<?php

namespace samuelreichor\llmify\services;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Pipeline\Pipeline;
use Craft;
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

    public function generateUrl(string $url): bool
    {
        $client = HttpClientBuilder::buildDefault();
        $request = $this->createRequest($url);
        $response = $client->request($request);

        return $response->getStatus() === 200;
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

        if ($response->getStatus() !== 200) {
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
            try {
                $request = $this->createRequest($url);
                $response = $client->request($request);

                if ($response->getStatus() === 200) {
                    // In headless mode Craft never renders the front end, so the
                    // Twig save side-effect does not run. Read the fetched body
                    // and convert it here instead.
                    if ($isHeadless && is_array($item) && $item['elementId'] !== null) {
                        $this->saveFromBody($response->getBody()->buffer(), $item['elementId'], $item['siteId']);
                    }

                    $this->generated++;
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
    protected function createRequest(string $url): Request
    {
        $request = new Request($url);
        $request->setHeader(Constants::HEADER_REFRESH, '1');
        $request->setTcpConnectTimeout($this->requestTimeout);
        $request->setTlsHandshakeTimeout($this->requestTimeout);
        $request->setTransferTimeout($this->requestTimeout);
        $request->setInactivityTimeout($this->requestTimeout);

        return $request;
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
