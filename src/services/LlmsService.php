<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\ContentSettings;
use samuelreichor\llmify\models\GlobalSettings;
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
        if (!$this->globalSettings->isEnabled() || !$this->globalSettings->enableLlmsTxt) {
            return '';
        }

        return HelperService::cached([Constants::CACHE_TAG, 'llms-txt', $this->currentSiteId], function() {
            $markdown = $this->constructIntro();
            $markdown .= $this->constructAllUrls();
            $markdown .= $this->constructSocialSection();
            $markdown .= $this->constructFooter();

            return $markdown;
        });
    }

    public function getLlmsFullContent(): string
    {
        if (!$this->globalSettings->isEnabled() || !$this->globalSettings->enableLlmsFullTxt) {
            return '';
        }

        return self::getStoredLlmsFull($this->currentSiteId)['content'] ?? '';
    }

    /**
     * @return array{content: string, dateGenerated: int}|null
     */
    public static function getStoredLlmsFull(int $siteId): ?array
    {
        $stored = Craft::$app->getCache()->get(self::llmsFullCacheKey($siteId));

        return is_array($stored) ? $stored : null;
    }

    /**
     * Rendering every page takes too long for a web request, so this runs from
     * the console (e.g. a cron job).
     *
     * @return int|null The number of pages included, or null when the file is disabled for this site.
     * @throws \yii\base\Exception
     */
    public function generateLlmsFullContent(): ?int
    {
        if (!$this->globalSettings->isEnabled() || !$this->globalSettings->enableLlmsFullTxt) {
            return null;
        }

        $pages = $this->collectPageMarkdowns();

        $markdown = $this->constructIntro();
        foreach ($pages as $pageContent) {
            $markdown .= "{$pageContent}\n\n---\n\n";
        }
        $markdown .= $this->constructSocialSection();
        $markdown .= $this->constructFooter();

        Craft::$app->getCache()->set(self::llmsFullCacheKey($this->currentSiteId), [
            'content' => $markdown,
            'dateGenerated' => time(),
        ], 0);

        return count($pages);
    }

    private static function llmsFullCacheKey(int $siteId): array
    {
        return [Constants::CACHE_TAG, 'llms-full', $siteId];
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

            $elements = $markdownService->findElementsForContentSetting($contentSetting, $this->currentSiteId);

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

                $metadata = new MetadataService($element);
                $title = $metadata->getLlmTitle();
                $description = $metadata->getLlmDescription();

                $url = $shouldUseRealUrls ? $element->getUrl() : HelperService::getMarkdownUrl($element->uri);
                if ($url === null) {
                    continue;
                }

                $content .= $this->constructUrl($title, $url, $description);
            }
        }

        return $content;
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

    /**
     * Builds a `## Social` section from the social links configured in the site
     * settings. While no custom links are stored, SEOmatic's "Same As URLs" are
     * used. Returns an empty string when the setting is disabled or no usable
     * links are configured.
     */
    private function constructSocialSection(): string
    {
        if (!$this->globalSettings->includeSocialLinks) {
            return '';
        }

        $links = $this->globalSettings->socialLinks;
        if (empty($links)) {
            $links = HelperService::getSeomaticSocialLinks($this->currentSiteId);
        }

        $content = '';
        foreach ($links as $link) {
            $siteName = trim((string)($link['siteName'] ?? ''));
            $url = trim((string)($link['url'] ?? ''));

            if ($siteName === '' || $url === '') {
                continue;
            }

            $content .= "- [{$siteName}]({$url})\n";
        }

        if ($content === '') {
            return '';
        }

        return "\n## Social\n\n{$content}";
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
     * Returns the markdown of every servable page of the site, in the order of
     * the content settings. In headless mode the pages are fetched from the
     * front end. Otherwise their `.md` URLs are requested, since site templates
     * can only be rendered reliably in a web request.
     *
     * @return string[]
     * @throws \yii\base\Exception
     */
    private function collectPageMarkdowns(): array
    {
        $markdownService = Llmify::getInstance()->markdown;
        $isHeadless = Llmify::getInstance()->getSettings()->headlessMode;
        $elements = $markdownService->getServableElements($this->currentSiteId);

        if ($isHeadless) {
            $pages = array_map(fn($element) => $markdownService->getPageMarkdown($element), $elements);
        } else {
            $urls = array_map(fn($element) => HelperService::getMarkdownUrl($element->uri, $element->siteId), $elements);
            $pages = array_values(Llmify::getInstance()->request->fetchAll($urls, $this->currentSiteId));
        }

        return array_values(array_filter($pages, fn($page) => $page !== null && $page !== ''));
    }
}
