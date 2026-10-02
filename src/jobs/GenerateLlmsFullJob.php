<?php

namespace samuelreichor\llmify\jobs;

use Craft;
use craft\queue\BaseJob;
use samuelreichor\llmify\services\LlmsService;

class GenerateLlmsFullJob extends BaseJob
{
    public function execute($queue): void
    {
        LlmsService::generateLlmsFullForAllSites();
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('llmify', 'Generating llms-full.txt');
    }
}
