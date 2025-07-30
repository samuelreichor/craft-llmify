<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\GlobalSettings;
use samuelreichor\llmify\models\Page;
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
        $this->globalSettings = Llmify::getInstance()->settings->getAndSetGlobalSettings($this->currentSiteId);
    }

    public function getMarkdown(): string
    {
        $markdown = '';
        $llmTitle = $this->globalSettings->llmTitle;
        $llmDescription = $this->globalSettings->llmDescription;

        if ($llmTitle) {
            $markdown .= "# {$llmTitle}\n";
        }

        if ($llmDescription) {
            $markdown .= "{$llmDescription}\n\n";
        }

        $markdown .= $this->constructAllUrls();

        return $markdown;
    }

    /**
     * @throws SiteNotFoundException
     */
    public function getMarkdownForUri(string $uri): string
    {
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
        $pages = PageRecord::find()->where(['siteId' => $this->currentSiteId])->orderBy('sectionId')->all();
        foreach ($pages as $page) {
            /**
             * @var Page $page
             */
            $entryUri = $page->entryMeta['uri'];
            $markdownUrl = "{$currentSiteUrl}raw/{$entryUri}.md";
            $content .= "[{$page->title}]({$markdownUrl}): {$page->description}.\n";
        }

        return $content;
    }
}
