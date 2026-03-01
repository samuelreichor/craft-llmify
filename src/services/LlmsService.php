<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\ContentSettings;
use samuelreichor\llmify\models\GlobalSettings;
use samuelreichor\llmify\models\Page;
use yii\db\Exception;

class LlmsService extends Component
{
    public ?GlobalSettings $globalSettings;
    public int $currentSiteId;

    /**
     * @throws SiteNotFoundException
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->currentSiteId = Llmify::getInstance()->helper->getCurrentCpSiteId();
        $this->globalSettings = Llmify::getInstance()->settings->getGlobalSetting($this->currentSiteId);
    }

    /**
     * @throws \yii\base\Exception
     */
    public function getLlmsTxtContent(): string
    {
        if (!$this->globalSettings->isEnabled()) {
            return '';
        }
        $markdown = $this->constructIntro();
        $markdown .= $this->constructAllUrls();
        $markdown .= $this->constructFooter();

        return $markdown;
    }


    /**
     * @throws Exception
     */
    public function getLlmsFullContent(): string
    {
        if (!$this->globalSettings->isEnabled()) {
            return '';
        }

        $markdown = $this->constructIntro();
        $markdown .= $this->constructAllPages();
        $markdown .= $this->constructFooter();

        return $markdown;
    }

    public function constructIntro(): string
    {
        $markdown = '';
        $llmTitle = $this->globalSettings->llmTitle;
        $llmDescription = $this->globalSettings->llmDescription;

        if ($llmTitle) {
            $markdown .= "# {$llmTitle}\n\n";
        }

        if ($llmDescription) {
            $markdown .= "> {$llmDescription}\n\n";
        }
        return $markdown;
    }

    /**
     * @throws SiteNotFoundException
     */
    public function getMarkdownForUri(string $uri): string
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $markdownService = Llmify::getInstance()->markdown;

        $markdown = $markdownService->getRenderedMarkdown($uri, $siteId);

        if ($markdown !== null) {
            return $markdown;
        }

        // Try to generate on-the-fly if the entry exists
        $entry = Entry::find()->uri($uri)->siteId($siteId)->one();
        if ($entry && $entry->getUrl()) {
            try {
                Llmify::getInstance()->request->generateUrl($entry->getUrl());
            } catch (\Throwable $e) {
                Craft::warning("On-the-fly markdown generation failed for URI: {$uri}. " . $e->getMessage(), 'llmify');
                return '';
            }

            return $markdownService->getRenderedMarkdown($uri, $siteId) ?? '';
        }

        return '';
    }


    /**
     * @throws \yii\base\Exception
     */
    private function constructAllUrls(): string
    {
        $content = '';
        $shouldUseRealUrls = Llmify::getInstance()->getSettings()->isRealUrlLlm;
        $settingsService = Llmify::getInstance()->settings;
        $markdownService = Llmify::getInstance()->markdown;
        $allSettings = $settingsService->getContentSettingsBySiteId($this->currentSiteId);

        foreach ($allSettings as $contentSetting) {
            if (!$markdownService->isSectionServable($contentSetting->sectionId, $this->currentSiteId)) {
                continue;
            }

            $entries = Entry::find()
                ->sectionId($contentSetting->sectionId)
                ->siteId($this->currentSiteId)
                ->all();

            if (empty($entries)) {
                continue;
            }

            $content .= $this->constructSectionHeader($contentSetting);

            foreach ($entries as $entry) {
                $metadata = new MetadataService($entry);
                $title = $metadata->getLlmTitle();
                $description = $metadata->getLlmDescription();
                $url = $shouldUseRealUrls ? $entry->getUrl() : HelperService::getMarkdownUrl($entry->uri);

                $content .= $this->constructUrl($title, $url, $description);
            }
        }

        return $content;
    }

    private function constructSectionHeader(ContentSettings $metaData): string
    {
        $content = '';
        if ($metaData->llmSectionTitle) {
            $content .= "\n## $metaData->llmSectionTitle\n\n";
        }

        if ($metaData->llmSectionDescription) {
            $content .= "$metaData->llmSectionDescription\n\n";
        }

        return $content;
    }

    private function constructUrl(string $title, string $url, string $description): string
    {
        if (!$title || !$url) {
            return '';
        }

        $markdownUrl = "[{$title}]({$url})";
        $descriptionPart = $description ? ": {$description}" : '';

        return "- {$markdownUrl}{$descriptionPart}\n";
    }

    private function constructFooter(): string
    {
        $llmNote = $this->globalSettings->llmNote;

        $content = '';
        if ($llmNote) {
            $content .= "\n## Notes\n\n";
            $content .= "$llmNote";
        }

        return $content;
    }

    /**
     * @throws Exception
     */
    private function constructAllPages(): string
    {
        $content = '';
        $settings = Llmify::getInstance()->getSettings();
        $frontMatterService = Llmify::getInstance()->frontMatter;
        $pages = Llmify::getInstance()->markdown->getActivePagesForSite($this->currentSiteId);

        foreach ($pages as $page) {
            /**
             * @var Page $page
             */
            $pageContent = $page->content;

            if ($settings->frontMatterInFullTxt) {
                $entry = Entry::find()->id($page->entryId)->siteId($this->currentSiteId)->one();
                $pageContent = $frontMatterService->prependFrontMatter($pageContent, $page, $entry);
            }

            $content .= "{$pageContent}\n\n---\n\n";
        }

        return $content;
    }
}
