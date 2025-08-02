<?php

namespace samuelreichor\llmify\jobs;

use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use craft\queue\BaseBatchedJob;
use craft\db\QueryBatcher;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\RefreshData;

class RefreshMarkdownJob extends BaseBatchedJob
{

    public RefreshData $data;

    protected function loadData(): QueryBatcher
    {
        $elementIds = $this->data->getElementIds(Entry::class);
        $siteIds = $this->data->getSiteIds();

        $query = Entry::find()
            ->id($elementIds)
            ->siteId($siteIds)
            ->orderBy('id ASC');

        return new QueryBatcher($query);
    }

    /**
     * @param Entry $item
     * @throws SiteNotFoundException
     */
    protected function processItem($item): void
    {
        Llmify::getInstance()->markdown->generateForEntry($item);
    }

    public function defaultDescription(): string
    {
        $count = count($this->data->getElementIds(Entry::class));
        return "Refreshing LLMify Markdowns";
    }
}
