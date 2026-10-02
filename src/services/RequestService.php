<?php

namespace samuelreichor\llmify\services;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Pipeline\Pipeline;
use Craft;
use craft\helpers\App;
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

    public function __construct()
    {
        parent::__construct();
        $settings = Llmify::getInstance()->getSettings();
        $this->concurrentRequests = $settings->concurrentRequests;
        $this->requestTimeout = $settings->requestTimeout;
    }

    /**
     * Fetches a single URL and converts its rendered HTML body to markdown.
     * Used in headless mode. Returns null when the URL does not respond with
     * a 200.
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

    /**
     * @param string[] $urls
     * @return array<string, string|null>
     */
    public function fetchAll(array $urls, int $siteId): array
    {
        $client = HttpClientBuilder::buildDefault();

        $results = Pipeline::fromIterable($urls)
            ->concurrent($this->concurrentRequests)
            ->map(function(string $url) use ($client, $siteId) {
                try {
                    $response = $client->request($this->createRequest($url, $siteId));
                    $status = $response->getStatus();

                    if ($status !== 200) {
                        $this->logUnexpectedStatus($url, $status);
                        return [$url, null];
                    }

                    return [$url, $response->getBody()->buffer()];
                } catch (Throwable $e) {
                    Craft::warning("Failed fetching {$url}. " . $e->getMessage(), 'llmify');
                    return [$url, null];
                }
            })
            ->toArray();

        return array_column($results, 1, 0);
    }

    /**
     * Builds the request used to fetch a page. It carries what the site needs
     * to let the request through: a Craft site token while the system is
     * offline (`isSystemLive: false`), and HTTP Basic Auth credentials when
     * the site is protected that way. The site token is only sent when needed,
     * since Craft appends it to every URL in the response.
     */
    protected function createRequest(string $url, ?int $siteId = null): Request
    {
        $request = new Request($url);

        if ($siteId !== null && !Craft::$app->getIsLive()) {
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

        Craft::warning(trim("Skipped {$url}: the site responded with HTTP {$status}. {$hint}"), 'llmify');
    }
}
