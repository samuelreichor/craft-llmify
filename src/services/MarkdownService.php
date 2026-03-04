<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
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
    public function saveMarkdown(string $markdown, int $elementId, int $siteId): void
    {
        $element = Craft::$app->elements->getElementById($elementId, null, $siteId);

        if (!$element) {
            throw new Exception('Element not found, unable to save markdown for element ' . $elementId);
        }

        $groupId = HelperService::getGroupIdForElement($element);
        $elementType = HelperService::getElementTypeForElement($element);

        $pageEntry = PageRecord::findOne(['elementId' => $elementId, 'siteId' => $siteId]);
        $metaDataService = new MetadataService($element);

        if (!$pageEntry) {
            $pageEntry = new PageRecord();
            $pageEntry->elementId = $element->id;
            $pageEntry->elementType = $elementType;
            $pageEntry->groupId = $groupId;
            $pageEntry->metadataId = $metaDataService->getMetaContentId();
            $pageEntry->siteId = $element->getSite()->id;
        }

        $pageEntry->title = $metaDataService->getLlmTitle();
        $pageEntry->description = $metaDataService->getLlmDescription();
        $pageEntry->content = $markdown;
        $pageEntry->dateUpdated = new Expression('NOW()');
        $pageEntry->elementMeta = [
            "fullUrl" => $element->getUrl(),
            "uri" => $element->uri,
        ];
        $pageEntry->save();
    }

    public function getMarkdown(string $uri, int $siteId): ?Page
    {
        $result = $this->_createPageQuery()
            ->where(['siteId' => $siteId])
            ->andWhere("JSON_UNQUOTE(JSON_EXTRACT(elementMeta, '$.uri')) = :uri", [':uri' => $uri])
            ->one();

        return $result ? new Page($result) : null;
    }

    public function getRenderedMarkdown(string $uri, int $siteId, ?ElementInterface $element = null): ?string
    {
        $page = $this->getMarkdown($uri, $siteId);

        if (!$page) {
            return null;
        }

        if (!$this->isGroupServable($page->groupId, $siteId, $page->elementType)) {
            return null;
        }

        $element = $element ?? Craft::$app->elements->getElementById($page->elementId, $page->elementType, $siteId);

        return Llmify::getInstance()->frontMatter->prependFrontMatter($page->content, $page, $element);
    }

    public function resolveAutoServeMarkdown(): ?string
    {
        if ($this->entryId === null || $this->siteId === null) {
            return null;
        }

        $element = Craft::$app->elements->getElementById($this->entryId, null, $this->siteId);

        if (!$element) {
            return null;
        }

        $groupId = HelperService::getGroupIdForElement($element);
        $elementType = HelperService::getElementTypeForElement($element);

        if ($groupId === null || !$this->isGroupServable($groupId, $this->siteId, $elementType)) {
            return null;
        }

        $this->processContentBlocks();

        return $this->getRenderedMarkdown($element->uri, $this->siteId, $element);
    }

    public function isGroupServable(int $groupId, int $siteId, ?string $elementType = null): bool
    {
        $elementType = $elementType ?? Entry::class;
        $settings = Llmify::getInstance()->settings;

        return $settings->getGlobalSetting($siteId)->isEnabled()
            && $settings->getContentSetting($groupId, $siteId, $elementType)->isEnabled();
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
            $groupKey = ($page['elementType'] ?? Entry::class) . ':' . $page['groupId'];
            $groupedPages[$groupKey][] = new Page($page);
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
        foreach ($groupedPages as $groupKey => $pages) {
            [$elementType, $groupId] = explode(':', $groupKey, 2);
            if (!$this->isGroupServable((int)$groupId, $siteId, $elementType)) {
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

        // Remove empty links left behind after image/node removal (e.g. [](url) or [ ](url))
        $markdownRaw = preg_replace('/\[\s*]\([^)]*\)/', '', $markdownRaw);

        // Decode HTML entities (e.g. &amp; → &, &nbsp; → space)
        $markdownRaw = html_entity_decode($markdownRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
            ->orderBy('groupId ASC')
            ->select([
                'siteId',
                'groupId',
                'elementId',
                'elementType',
                'metadataId',
                'content',
                'title',
                'description',
                'elementMeta',
            ])
            ->from([Constants::TABLE_PAGES]);
    }
}
