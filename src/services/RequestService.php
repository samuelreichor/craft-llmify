<?php

namespace samuelreichor\llmify\services;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Pipeline\Pipeline;
use Craft;
use yii\base\Component;
use Amp\Http\Client\Request;

/**
 * Request Service
 */
class RequestService extends Component
{
    /**
     * @var int The max number of concurrent requests.
     */
    public int $concurrency = 3;

    /**
     * @var int The timeout for requests in seconds.
     */
    public int $timeout = 60;
    private int $generated = 0;

    public function generateUrlsWithProgress(array $urls, callable $setProgressHandler = null): void
    {

        $this->generateWithProgress($urls, $setProgressHandler, 0, count($urls));
    }

    public function generateWithProgress(array $urls, callable $setProgressHandler, int $count, int $total ): void
    {
        $client = HttpClientBuilder::buildDefault();

        $concurrentIterator = Pipeline::fromIterable($urls)
            ->concurrent($this->concurrency);

        foreach ($concurrentIterator as $url) {
            $count++;
            try {
                $request = $this->createRequest($url);
                $response = $client->request($request);

                if ($response->getStatus() === 200) {
                    $this->generated++;
                    Craft::debug("Generated URL {$url} response");
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

        // Set all timeout types, since at least two have been reported:
        // https://github.com/putyourlightson/craft-blitz/issues/467#issuecomment-1410308809
        $request->setTcpConnectTimeout($this->timeout);
        $request->setTlsHandshakeTimeout($this->timeout);
        $request->setTransferTimeout($this->timeout);
        $request->setInactivityTimeout($this->timeout);

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
