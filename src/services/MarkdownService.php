<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\db\Query as DbQuery;
use craft\elements\Entry;
use Exception;
use League\HTMLToMarkdown\HtmlConverter;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\Page;
use samuelreichor\llmify\records\PageRecord;
use yii\base\InvalidConfigException;
use yii\db\Expression;

class MarkdownService extends Component
{
    public array $contentBlocks = [];
    public array $excludedContentBlocks = [];
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

    public function addExcludedContentBlock(string $html, int $entryId, int $siteId): void
    {
        $this->excludedContentBlocks[] = $html;
        if ($this->entryId === null) {
            $this->entryId = $entryId;
            $this->siteId = $siteId;
        }
    }

    /**
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     * @throws CircularException
     * @throws StrictException
     */
    public function getCombinedHtml(): string
    {
        $fullHtml = implode('', $this->contentBlocks);

        // removes content in excludeLlmify tags
        if (!empty($this->excludedContentBlocks)) {
            $fullHtml = str_replace($this->excludedContentBlocks, '', $fullHtml);
        }

        return $this->removeTags($fullHtml);
    }

    public function clearBlocks(): void
    {
        $this->contentBlocks = [];
        $this->excludedContentBlocks = [];
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
        $pageEntry->dateUpdated = new Expression('NOW()');
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

    public function getRenderedMarkdown(string $uri, int $siteId, ?Entry $entry = null): ?string
    {
        $page = $this->getMarkdown($uri, $siteId);

        if (!$page) {
            return null;
        }

        if (!$this->isSectionServable($page->sectionId, $siteId)) {
            return null;
        }

        $entry = $entry ?? Entry::find()->id($page->entryId)->siteId($siteId)->one();

        return Llmify::getInstance()->frontMatter->prependFrontMatter($page->content, $page, $entry);
    }

    public function resolveAutoServeMarkdown(): ?string
    {
        if ($this->entryId === null || $this->siteId === null) {
            return null;
        }

        $entry = Entry::find()->id($this->entryId)->siteId($this->siteId)->one();

        if (!$entry || !$this->isSectionServable($entry->section->id, $this->siteId)) {
            return null;
        }

        $this->processContentBlocks();

        return $this->getRenderedMarkdown($entry->uri, $this->siteId, $entry);
    }

    public function isSectionServable(int $sectionId, int $siteId): bool
    {
        $settings = Llmify::getInstance()->settings;

        return $settings->getGlobalSetting($siteId)->isEnabled()
            && $settings->getContentSetting($sectionId, $siteId)->isEnabled();
    }

    public function processContentBlocks(): void
    {
        $html = $this->getCombinedHtml();

        if (!empty($html)) {
            $this->process($html);
        }
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
        foreach ($groupedPages as $sectionId => $pages) {
            if (!$this->isSectionServable($sectionId, $siteId)) {
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

    /**
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     * @throws CircularException
     * @throws StrictException
     */
    private function removeTags(string $html): string
    {
        $dom = new Dom();
        $dom->loadStr($html);
        $excludedClass = $this->getExcludeClass();
        $nodesToRemove = $dom->find($excludedClass);

        foreach ($nodesToRemove as $node) {
            $node->delete();
        }

        return $dom;
    }

    private function getExcludeClass(): string
    {
        $excludedClasses = Llmify::getInstance()->getSettings()->excludeClasses;
        return implode(',', array_map(function($n) {
            return ".{$n['classes']}";
        }, $excludedClasses));
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
