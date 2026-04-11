<?php

namespace samuelreichor\llmify\services;

use Craft;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
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
        $client = Craft::createGuzzleClient([
            'timeout' => $this->requestTimeout,
            'connect_timeout' => $this->requestTimeout,
        ]);

        $request = new Request('GET', $url, [Constants::HEADER_REFRESH => '1']);
        $response = $client->send($request);

        return $response->getStatusCode() === 200;
    }

    public function generateUrlsWithProgress(array $urls, callable $setProgressHandler = null): void
    {
        $this->generateWithProgress($urls, $setProgressHandler, 0, count($urls));
    }

    public function generateWithProgress(array $urls, callable $setProgressHandler, int $count, int $total): void
    {
        $client = Craft::createGuzzleClient([
            'timeout' => $this->requestTimeout,
            'connect_timeout' => $this->requestTimeout,
        ]);

        $requests = function() use ($urls) {
            foreach ($urls as $url) {
                yield new Request('GET', $url, [Constants::HEADER_REFRESH => '1']);
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => $this->concurrentRequests,
            'fulfilled' => function(ResponseInterface $response) use (&$count, $total, $setProgressHandler) {
                $count++;

                if ($response->getStatusCode() === 200) {
                    $this->generated++;
                }

                if (is_callable($setProgressHandler)) {
                    $this->callProgressHandler($setProgressHandler, $count, $total);
                }
            },
            'rejected' => function(RequestException $exception) use (&$count, $total, $setProgressHandler) {
                $count++;
                Craft::error("Failed generating URL. " . $exception->getMessage());

                if (is_callable($setProgressHandler)) {
                    $this->callProgressHandler($setProgressHandler, $count, $total);
                }
            },
        ]);

        $pool->promise()->wait();
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
