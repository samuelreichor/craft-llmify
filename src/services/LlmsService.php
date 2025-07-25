<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use samuelreichor\llmify\models\Page;
use samuelreichor\llmify\records\PageRecord;

class LlmsService extends Component
{
    /**
     * @throws SiteNotFoundException
     */
    public function getMarkdown(): string
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $title = 'LLMify Title';
        $description = 'LLMIFY Description';

        return 'Site is ' . $siteId;
    }

    /**
     * @throws SiteNotFoundException
     */
    public function getMarkdownForUri(string $uri): string
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $page = PageRecord::find()->where(['siteId' => $siteId])->one();
        return $page->content;
    }

    private function constructAllUrls(int $siteId): string
    {
        $content = '';

        $pages = PageRecord::find()->where(['siteId' => $siteId])->all();
        foreach ($pages as $page) {
            $entryUrl = Entry::find()->id($page->entryId)->siteId($siteId)->one()->getUrl();
            $content .= "[{$page->title}]({$entryUrl}){$page->description}\n";
        }

        return $content;
    }
}
