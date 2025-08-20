<?php

namespace samuelreichor\llmify\services;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Pipeline\Pipeline;
use Craft;
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

    public function generateUrlsWithProgress(array $urls, callable $setProgressHandler = null): void
    {
        $this->generateWithProgress($urls, $setProgressHandler, 0, count($urls));
    }

    public function generateWithProgress(array $urls, callable $setProgressHandler, int $count, int $total): void
    {
        $client = HttpClientBuilder::buildDefault();

        $concurrentIterator = Pipeline::fromIterable($urls)
            ->concurrent($this->concurrentRequests);

        foreach ($concurrentIterator as $url) {
            $count++;
            try {
                $request = $this->createRequest($url);
                $response = $client->request($request);

                if ($response->getStatus() === 200) {
                    $this->generated++;
                }

                if (is_callable($setProgressHandler)) {
                    $this->callProgressHandler($setProgressHandler, $count, $total);
                }
            } catch (HttpException $exception) {
                Craft::error("Failed generating URL {$url}. " . $exception->getMessage());
            }
        }
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
