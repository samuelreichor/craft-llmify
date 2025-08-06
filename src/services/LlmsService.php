<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\GlobalSettings;
use samuelreichor\llmify\models\Page;
use samuelreichor\llmify\records\ContentSettingRecord;
use samuelreichor\llmify\records\PageRecord;
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
        if(!$this->globalSettings->isEnabled()) {
            return '';
        }
        $markdown = $this->constructIntro();
        $markdown .= $this->constructAllUrls();
        $markdown .= $this->constructFooter();

        return $markdown;
    }


    public function getLlmsFullContent(): string
    {
        if(!$this->globalSettings->isEnabled()) {
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
        if(!$this->globalSettings->isEnabled()) {
            return '';
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $page = PageRecord::find()
            ->where(['siteId' => $siteId])
            ->andWhere("JSON_UNQUOTE(JSON_EXTRACT(entryMeta, '$.uri')) = :uri", [':uri' => $uri])
            ->one();

        if (!$page) {
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
        $groupedPages = $this->getGroupedPagesBySite( $this->currentSiteId);

        foreach ($groupedPages as $sectionId => $pages) {
            $content .= $this->constructSectionHeader($sectionId, $this->currentSiteId);
            foreach ($pages as $page) {
                /**
                 * @var Page $page
                 */
                $content .= ($shouldUseRealUrls ? $this->constructRealUrl($page, $currentSiteUrl) : $this->constructMdUrl($page, $currentSiteUrl));
            }
        }

        return $content;
    }

    private function constructSectionHeader(int $sectionId, int $currentSiteId): string
    {
        $metaData = ContentSettingRecord::find()
            ->where(['sectionId' => $sectionId, 'siteId' => $currentSiteId])
            ->select(['llmSectionTitle', 'llmSectionDescription'])
            ->one();

        $content = '';


        if ($metaData) {
            /**
             * @var ContentSettingRecord $metaData
             */
            if($metaData->llmSectionTitle) {
                $content .= "## $metaData->llmSectionTitle\n\n";
            }

            if($metaData->llmSectionDescription) {
                $content .= "$metaData->llmSectionDescription\n\n";
            }
        }
        return $content;

    }

    private function constructMdUrl(Page $page, string $currentSiteUrl): string
    {
        $entryUri = $page->entryMeta['uri'];
        $markdownUrl = "{$currentSiteUrl}raw/{$entryUri}.md";
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
            $content .= "\n## Notes\n";
            $content .= "\n$llmNote";
        }

        return $content;
    }

    private function constructAllPages(): string
    {
        $content = '';
        $pages = PageRecord::find()->where(['siteId' => $this->currentSiteId])->orderBy('sectionId')->all();
        foreach ($pages as $page) {
            /**
             * @var Page $page
             */
            $content .= "{$page->content}\n\n";
        }

        return $content;
    }

    private function getGroupedPagesBySite(int $siteId): array
    {
        $pageRecords = PageRecord::find()
            ->where(['siteId' => $siteId])
            ->orderBy('sectionId ASC')
            ->select([
                'entryId',
                'siteId',
                'sectionId',
                'metadataId',
                'content',
                'title',
                'description',
                'entryMeta',
            ])
            ->all();

        $groupedPages = [];

        foreach ($pageRecords as $page) {
            /**
             * @var PageRecord $page
             */
            $groupedPages[$page->sectionId][] = new Page($page->toArray());
        }

        return $groupedPages;
    }
}
