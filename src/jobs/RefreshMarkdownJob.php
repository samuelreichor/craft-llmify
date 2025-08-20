<?php

namespace samuelreichor\llmify\jobs;

use craft\helpers\Queue as QueueHelper;
use craft\queue\BaseBatchedJob;
use samuelreichor\llmify\batchers\SiteUrlBatcher;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\LlmifyRefreshData;
use yii\queue\Queue;

class RefreshMarkdownJob extends BaseBatchedJob
{
    public LlmifyRefreshData $data;

    private Queue $queue;

    public function execute($queue): void
    {
        // Patches that are later get higher priority
        if ($this->itemOffset === 0 && $this->priority > 0) {
            $this->priority--;
        }

        $this->queue = $queue;

        /** @var array $siteUrls */
        $siteUrls = $this->data()->getSlice($this->itemOffset, $this->batchSize);
        LLMify::getInstance()->request->generateUrlsWithProgress($siteUrls, [$this, 'setProgressHandler']);
        $this->itemOffset += count($siteUrls);

        // Spawn another job if there are more items
        if ($this->itemOffset < $this->totalItems()) {
            $nextJob = clone $this;
            $nextJob->batchIndex++;
            QueueHelper::push($nextJob, $this->priority, 0, $this->ttr, $queue);
        }
    }

    protected function loadData(): SiteUrlBatcher
    {
        return new SiteUrlBatcher($this->data->getUrls());
    }

    protected function processItem(mixed $item): void
    {
    }

    public function defaultDescription(): string
    {
        return "Refreshing LLMify Markdowns";
    }

    /**
     * Handles setting the progress.
     */
    public function setProgressHandler(int $count, int $total, string $label = null): void
    {
        $progress = $total > 0 ? ($count / $total) : 0;

        $this->setProgress($this->queue, $progress, $label);
    }
}
