<?php

namespace samuelreichor\llmify\batchers;

use craft\base\Batchable;

class SiteUrlBatcher implements Batchable
{
    public function __construct(
        private readonly array $siteUrls,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return count($this->siteUrls);
    }

    /**
     * @inheritdoc
     */
    public function getSlice(int $offset, int $limit): iterable
    {
        return array_slice($this->siteUrls, $offset, $limit);
    }
}
