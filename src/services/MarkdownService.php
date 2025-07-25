<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use Exception;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\models\Page;
use samuelreichor\llmify\records\PageRecord;
use yii\base\InvalidConfigException;
use craft\db\Query as DbQuery;
use samuelreichor\llmify\Llmify;
use League\HTMLToMarkdown\HtmlConverter;

class MarkdownService extends Component
{
    /**
     * @throws InvalidConfigException
     */
    public function process(string $html, int $entryId = null, int $siteId = null): void
    {
/*        if ($url === null) {
            $request = Craft::$app->getRequest();
            if ($request->getIsCpRequest() || $request->getIsConsoleRequest()) {
                Craft::error('The page didn`t process because no url was provided.' . $request->getUrl(), 'llmify');
                return;
            }
            $url = $request->getAbsoluteUrl();
        }*/

        $markdown = $this->htmlToMarkdown($html);
        $this->saveMarkdown($markdown, $entryId, $siteId);
    }

    /**
     * Generates and processes HTML for a specific entry.
     *
     * @param Entry $entry
     * @throws SiteNotFoundException
     */
    public function generateForEntry(Entry $entry): void
    {
        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;
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
        $converter = new HtmlConverter();
        $converter->getConfig()->setOption('strip_tags', true);
        $converter->getConfig()->setOption('header_style', 'atx');
        return $converter->convert($html);
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


        $pageEntry = $this->getPageForEntryId($entryId);
        if (!$pageEntry) {
            $pageEntry = new PageRecord();
        }

        $metaDataService = new MetadataService($entry);
        $pageEntry->entryId = $entryId;
        $pageEntry->sectionId = $entry->section->id;
        $pageEntry->metadataId = $metaDataService->getMetaContentId();
        $pageEntry->siteId = $entry->getSite()->id;
        $pageEntry->title = $metaDataService->getLlmTitleByEntry();
        $pageEntry->description = $metaDataService->getLlmDescriptionByEntry();
        $pageEntry->content = $markdown;

        $pageEntry->save();
    }

    public function getPageForEntryId(int $entryId): ?PageRecord
    {
        $result = $this->_createPageQuery()
            ->where(['entryId' => $entryId])
            ->one();

        return $result ? new PageRecord($result) : null;
    }

    private function _createPageQuery(): DbQuery
    {
        return (new DbQuery())
            ->select([
                'entryId',
                'sectionId',
            ])
            ->from([Constants::TABLE_PAGES]);
    }
}
