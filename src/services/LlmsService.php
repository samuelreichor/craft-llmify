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
        $site = Craft::$app->getSites()->getSiteById($this->currentSiteId);
        $llmTitle = HelperService::renderTwig($this->globalSettings->llmTitle, $site);
        $llmDescription = HelperService::renderTwig($this->globalSettings->llmDescription, $site);

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

        // Headless mode never generates on-the-fly via a Twig render; it relies
        // on pre-generation (or the convert endpoint).
        if (Llmify::getInstance()->getSettings()->headlessMode) {
            return '';
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

        // Index existing pages by elementId for fast lookup
        $groupedPages = $markdownService->getGroupedPagesForSite($this->currentSiteId);
        $pagesByElementId = [];
        foreach ($groupedPages as $pages) {
            foreach ($pages as $page) {
                $pagesByElementId[$page->elementId] = $page;
            }
        }

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
                if ($element->uri === null) {
                    continue;
                }

                if (HelperService::isElementExcluded($element)) {
                    continue;
                }

                // Use stored page data if available, otherwise resolve live
                $page = $pagesByElementId[$element->id] ?? null;
                if ($page) {
                    $title = $page->title;
                    $description = $page->description;
                } else {
                    $metadata = new MetadataService($element);
                    $title = $metadata->getLlmTitle();
                    $description = $metadata->getLlmDescription();
                }

                $url = $shouldUseRealUrls ? $element->getUrl() : HelperService::getMarkdownUrl($element->uri);
                if ($url === null) {
                    continue;
                }

                $content .= $this->constructUrl($title, $url, $description);
            }
        }

        return $content;
    }

    /**
     * Find elements for a given content setting.
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

    private function constructSectionHeader(ContentSettings $metaData): string
    {
        $content = '';
        $context = $this->resolveSectionContext($metaData);
        $llmSectionTitle = HelperService::renderTwig($metaData->llmSectionTitle, $context);
        $llmSectionDescription = HelperService::renderTwig($metaData->llmSectionDescription, $context);

        if ($llmSectionTitle) {
            $content .= "\n## {$llmSectionTitle}\n\n";
        }

        if ($llmSectionDescription) {
            $content .= "{$llmSectionDescription}\n\n";
        }

        return $content;
    }

    /**
     * Resolves the Twig render context for a section-level content setting.
     * Returns the section / product type if available, falling back to the site.
     */
    private function resolveSectionContext(ContentSettings $metaData): mixed
    {
        if ($metaData->elementType === Entry::class) {
            $section = Craft::$app->entries->getSectionById($metaData->groupId);
            if ($section) {
                return $section;
            }
        } elseif (HelperService::isCommerceInstalled() && $metaData->elementType === \craft\commerce\elements\Product::class) {
            $productType = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeById($metaData->groupId);
            if ($productType) {
                return $productType;
            }
        }

        return Craft::$app->getSites()->getSiteById($this->currentSiteId);
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
        $site = Craft::$app->getSites()->getSiteById($this->currentSiteId);
        $llmNote = HelperService::renderTwig($this->globalSettings->llmNote, $site);

        $content = '';
        if ($llmNote) {
            $content .= "\n## Notes\n\n";
            $content .= $llmNote;
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
}
