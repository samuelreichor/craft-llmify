<?php

namespace samuelreichor\llmify\jobs;

use Craft;
use craft\queue\BaseJob;

/**
 * Refresh Markdown queue job
 */
class RefreshMarkdown extends BaseJob
{
    /**
     * @var array
     */
    public array $data = [];

    function execute($queue): void
    {
        // ...

    }

    protected function defaultDescription(): ?string
    {
        return 'Refreshing the markdown';
    }
}
