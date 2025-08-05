<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\elements\Entry;
use Exception;
use samuelreichor\llmify\records\PageRecord;
use yii\base\InvalidConfigException;
use League\HTMLToMarkdown\HtmlConverter;

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

    private function htmlToMarkdown(string $html): string
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'header_style' => 'atx',
            'remove_nodes' => 'img picture style'
        ]);

        $markdownRaw = $converter->convert($html);
        return preg_replace('/(\n[ \t]*){2,}/', "\n\n", $markdownRaw);
    }
}
