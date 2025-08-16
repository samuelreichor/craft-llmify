<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\db\Query as DbQuery;
use craft\elements\Entry;
use Exception;
use League\HTMLToMarkdown\HtmlConverter;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\Page;
use samuelreichor\llmify\records\PageRecord;
use yii\base\InvalidConfigException;

class MarkdownService extends Component
{
    public array $contentBlocks = [];
    public ?int $entryId = null;
    public ?int $siteId = null;

    /**
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function process(string $html, int $entryId = null, int $siteId = null): void
    {
        $markdown = $this->htmlToMarkdown($html);
        $this->saveMarkdown($markdown, $entryId ?? $this->entryId, $siteId ?? $this->siteId);
    }

    public function addContentBlock(string $html, int $entryId, int $siteId): void
    {
        $this->contentBlocks[] = $html;
        if ($this->entryId === null) {
            $this->entryId = $entryId;
            $this->siteId = $siteId;
        }
    }

    public function getCombinedHtml(): string
    {
        return implode('', $this->contentBlocks);
    }

    public function clearBlocks(): void
    {
        $this->contentBlocks = [];
        $this->entryId = null;
        $this->siteId = null;
    }

    /**
     * @throws Exception
     */
    public function saveMarkdown(string $markdown, int $entryId, int $siteId): void
    {
        $entry = Entry::find()->id($entryId)->siteId($siteId)->one();

        if (!$entry) {
            throw new Exception('Entry not found, unable to save markdown for page ' . $entryId);
        }

        $pageEntry = PageRecord::findOne(['entryId' => $entryId, 'siteId' => $siteId]);
        $metaDataService = new MetadataService($entry);

        if (!$pageEntry) {
            $pageEntry = new PageRecord();
            $pageEntry->entryId = $entry->id;
            $pageEntry->sectionId = $entry->section->id;
            $pageEntry->metadataId = $metaDataService->getMetaContentId();
            $pageEntry->siteId = $entry->getSite()->id;
        }

        $pageEntry->title = $metaDataService->getLlmTitle();
        $pageEntry->description = $metaDataService->getLlmDescription();
        $pageEntry->content = $markdown;
        $pageEntry->entryMeta = [
            "fullUrl" => $entry->getUrl(),
            "uri" => $entry->uri,
        ];
        $pageEntry->save();
    }

    public function getMarkdown(string $uri, int $siteId): ?Page
    {
        $result = $this->_createPageQuery()
            ->where(['siteId' => $siteId])
            ->andWhere("JSON_UNQUOTE(JSON_EXTRACT(entryMeta, '$.uri')) = :uri", [':uri' => $uri])
            ->one();

        return $result ? new Page($result) : null;
    }

    public function getGroupedPagesForSite(int $siteId): array
    {
        $pageRecords = $this->_createPageQuery()
            ->where(['siteId' => $siteId])
            ->all();

        $groupedPages = [];

        foreach ($pageRecords as $page) {
            /**
             * @var PageRecord $page
             */
            $groupedPages[$page['sectionId']][] = new Page($page);
        }

        return $groupedPages;
    }

    /**
     * @throws \yii\db\Exception
     */
    public function getActivePagesForSite(int $siteId): array
    {
        $groupedPages = $this->getGroupedPagesForSite($siteId);

        $activePages = [];
        $settingsService = Llmify::getInstance()->settings;
        foreach ($groupedPages as $sectionId => $pages) {
            $contentSetting = $settingsService->getContentSetting($sectionId, $siteId);

            if (!$contentSetting->isEnabled()) {
                continue;
            }

            foreach ($pages as $page) {
                $activePages[] = $page;
            }
        }

        return $activePages;
    }

    private function htmlToMarkdown(string $html): string
    {
        $config = Llmify::getInstance()->getSettings()->markdownConfig;
        $converter = new HtmlConverter($config);

        $markdownRaw = $converter->convert($html);
        return preg_replace('/(\n[ \t]*){2,}/', "\n\n", $markdownRaw);
    }

    private function _createPageQuery(): DbQuery
    {
        return (new DbQuery())
            ->orderBy('sectionId ASC')
            ->select([
                'siteId',
                'sectionId',
                'entryId',
                'metadataId',
                'content',
                'title',
                'description',
                'entryMeta',
            ])
            ->from([Constants::TABLE_PAGES]);
    }
}
