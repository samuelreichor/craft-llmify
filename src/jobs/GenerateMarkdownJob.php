<?php

namespace samuelreichor\llmify\jobs;

use Craft;
use craft\queue\BaseJob;
use samuelreichor\llmify\Llmify;

class GenerateMarkdownJob extends BaseJob
{
    public function execute($queue): void
    {
        $markdownService = Llmify::getInstance()->markdown;

        foreach (Llmify::getInstance()->settings->getAllActiveGlobalSettingsIds() as $siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);

            if (!$site) {
                continue;
            }

            $markdownService->generate(
                $markdownService->getServableElements($siteId),
                fn(int $handled, int $total) => $this->setProgress($queue, $total > 0 ? $handled / $total : 1, Craft::t('llmify', '{site}: {handled} of {total} pages', [
                    'site' => $site->name,
                    'handled' => $handled,
                    'total' => $total,
                ])),
            );
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('llmify', 'Warming the LLMify markdown cache');
    }
}
