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

        // Look up the element to check if it's excluded
        $element = Entry::find()->uri($uri)->siteId($siteId)->one();
        if (!$element && HelperService::isCommerceInstalled()) {
            $element = \craft\commerce\elements\Product::find()->uri($uri)->siteId($siteId)->one();
        }

        if ($element && HelperService::isElementExcluded($element)) {
            return '';
        }

        $markdown = $markdownService->getRenderedMarkdown($uri, $siteId);

        if ($markdown !== null) {
            return $markdown;
        }

        if ($element && $element->getUrl()) {
            try {
                Llmify::getInstance()->request->generateUrl($element->getUrl());
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
            if (!$markdownService->isGroupServable($contentSetting->groupId, $this->currentSiteId, $contentSetting->elementType)) {
                continue;
            }

            $elements = $this->findElementsForContentSetting($contentSetting);

            if (empty($elements)) {
                continue;
            }

            $content .= $this->constructSectionHeader($contentSetting);

            foreach ($elements as $element) {
                if (HelperService::isElementExcluded($element)) {
                    continue;
                }

                $metadata = new MetadataService($element);
                $title = $metadata->getLlmTitle();
                $description = $metadata->getLlmDescription();
                $url = $shouldUseRealUrls ? $element->getUrl() : HelperService::getMarkdownUrl($element->uri);

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
            $element = Craft::$app->elements->getElementById($page->elementId, $page->elementType, $this->currentSiteId);

            if ($element && HelperService::isElementExcluded($element)) {
                continue;
            }

            $pageContent = $page->content;

            if ($settings->frontMatterInFullTxt && $element) {
                $pageContent = $frontMatterService->prependFrontMatter($pageContent, $page, $element);
            }

            $content .= "{$pageContent}\n\n---\n\n";
        }

        return $content;
    }

    /**
     * Find elements for a given content setting
     *
     * @return array
     */
    private function findElementsForContentSetting(ContentSettings $contentSetting): array
    {
        if ($contentSetting->elementType === Entry::class) {
            return Entry::find()
                ->sectionId($contentSetting->groupId)
                ->siteId($this->currentSiteId)
                ->all();
        }

        if (HelperService::isCommerceInstalled() && $contentSetting->elementType === \craft\commerce\elements\Product::class) {
            return \craft\commerce\elements\Product::find()
                ->typeId($contentSetting->groupId)
                ->siteId($this->currentSiteId)
                ->all();
        }

        return [];
    }
}
