<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use Exception;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\records\PageRecord;
use yii\base\InvalidConfigException;
use League\HTMLToMarkdown\HtmlConverter;

class MarkdownService extends Component
{
    public array $contentBlocks = [];

    /**
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function process(string $html, int $entryId = null, int $siteId = null): void
    {
        // dont process markdown for site requests
        if(!(Craft::$app->request->getIsCpRequest() || Craft::$app->request->getIsConsoleRequest())) {
            return;
        }
        $markdown = $this->htmlToMarkdown($html);
        $this->saveMarkdown($markdown, $entryId, $siteId);
    }

    public function addContentBlock(string $html): void
    {
        $this->contentBlocks[] = $html;
    }

    public function getCombinedHtml(): string
    {
        return implode('', $this->contentBlocks);
    }

    public function clearBlocks(): void
    {
        $this->contentBlocks = [];
    }

    /**
     * Generates and processes HTML for a specific entry.
     *
     *
     * @param Entry $entry
     * @throws SiteNotFoundException
     */
    public function generateForEntry(Entry $entry): void
    {
        $currentSiteId = Llmify::getInstance()->helper->getCurrentCpSiteId();
        $templatePath = $entry->section->getSiteSettings()[$currentSiteId]->template;

        try {
            $view = Craft::$app->getView();
            $originalMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
            $view->renderTemplate($templatePath, [
                'entry' => $entry,
                'url' => $entry->getUrl(),
            ]);

            $view->setTemplateMode($originalMode);
        } catch (\Throwable $e) {
            Craft::error('Failed to render template for llmify: ' . $e->getMessage(), 'llmify');
        }
    }

    public function htmlToMarkdown(string $html): string
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'header_style' => 'atx',
        ]);

        $markdownRaw = $converter->convert($html);
        return preg_replace('/(\n[ \t]*){2,}/', "\n\n", $markdownRaw);
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
}
