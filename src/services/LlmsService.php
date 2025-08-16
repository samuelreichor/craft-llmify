<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
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
     * @throws Exception
     */
    public function getMarkdownForUri(string $uri): string
    {
        if (!$this->globalSettings->isEnabled()) {
            return '';
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $page = Llmify::getInstance()->markdown->getMarkdown($uri, $siteId);

        if (!$page) {
            return '';
        }

        $contentSetting = Llmify::getInstance()->settings->getContentSetting($page->sectionId, $siteId);

        if (!$contentSetting->isEnabled()) {
            return '';
        }

        return $page->content;
    }


    /**
     * @throws \yii\base\Exception
     */
    private function constructAllUrls(): string
    {
        $content = '';
        $currentSiteUrl = UrlHelper::siteUrl();
        $shouldUseRealUrls = Llmify::getInstance()->getSettings()->isRealUrlLlm;
        $settingsService = Llmify::getInstance()->settings;
        $groupedPages = Llmify::getInstance()->markdown->getGroupedPagesForSite($this->currentSiteId);

        foreach ($groupedPages as $sectionId => $pages) {
            $contentSetting = $settingsService->getContentSetting($sectionId, $this->currentSiteId);
            if (!$contentSetting->isEnabled()) {
                continue;
            }
            $content .= $this->constructSectionHeader($contentSetting);
            foreach ($pages as $page) {
                /**
                 * @var Page $page
                 */
                $content .= ($shouldUseRealUrls ? $this->constructRealUrl($page, $currentSiteUrl) : $this->constructMdUrl($page, $currentSiteUrl));
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

    private function constructMdUrl(Page $page, string $currentSiteUrl): string
    {
        $entryUri = $page->entryMeta['uri'];
        $mdPrefix = Llmify::getInstance()->getSettings()->markdownUrlPrefix;
        $markdownUrl = "{$currentSiteUrl}{$mdPrefix}/{$entryUri}.md";
        return "- [{$page->title}]({$markdownUrl}): {$page->description}.\n";
    }

    private function constructRealUrl(Page $page, string $currentSiteUrl): string
    {
        $entryUri = $page->entryMeta['uri'];

        if ($entryUri === '__home__') {
            $entryUri = '';
        }
        $realUrl = "{$currentSiteUrl}{$entryUri}";
        return "- [{$page->title}]({$realUrl}): {$page->description}.\n";
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
        $pages = Llmify::getInstance()->markdown->getActivePagesForSite($this->currentSiteId);
        foreach ($pages as $page) {
            /**
             * @var Page $page
             */
            $content .= "{$page->content}\n\n---\n\n";
        }

        return $content;
    }
}
