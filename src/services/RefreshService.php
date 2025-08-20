<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Query as DbQuery;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use samuelreichor\llmify\behaviors\LlmifyChangedBehavior;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\jobs\RefreshMarkdownJob;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\LlmifyRefreshData;
use samuelreichor\llmify\records\PageRecord;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\db\Exception;

class RefreshService extends Component
{
    public LlmifyRefreshData $llmifyRefreshData;

    public function init(): void
    {
        parent::init();
        $this->reset();
    }

    public function reset(): void
    {
        $this->llmifyRefreshData = new LlmifyRefreshData();
    }

    /**
     * @throws Exception
     * @throws \yii\base\Exception
     */
    public function addElement(ElementInterface $element): void
    {
        if (!$this->isRefreshableElement($element)) {
            return;
        }

        // Get the custom behavior to decide if the Markdown should be refreshed.
        /** @var LlmifyChangedBehavior|null $elementChanged */
        $elementChanged = $element->getBehavior(LlmifyChangedBehavior::BEHAVIOR_NAME);
        $pageRecordExists = PageRecord::find()->where(['id' => $element->getId()])->exists();

        // only check if a page record exists, otherwise this validation is faulty
        if ($elementChanged !== null && $pageRecordExists) {
            // Delete Markdowns if entry gets deleted or deactivated
            if ($elementChanged->hasBeenDeleted()
                || ($elementChanged->hasStatusChanged() && !$elementChanged->hasRefreshableStatus())) {
                $this->deleteElement($elementChanged->originalElement);
            }

            // Don't refresh Markdown if the element has not changed.
            if (!$elementChanged->hasChanged()) {
                return;
            }

            // Don't refresh Markdown if the element has not a refreshable status (and status has not changed).
            if (!$elementChanged->hasStatusChanged() && !$elementChanged->hasRefreshableStatus()) {
                return;
            }
        }

        $this->llmifyRefreshData->addSiteId($element->siteId);
        $this->llmifyRefreshData->addUrl($element->getUrl());
        $this->llmifyRefreshData->addElementId($element->id, $element::class);
    }

    public function refresh(): void
    {
        Craft::debug(json_encode($this->llmifyRefreshData), 'llmify');
        if ($this->llmifyRefreshData->isEmpty()) {
            return;
        }

        Craft::debug(json_encode($this->llmifyRefreshData), 'llmify');
        $job = new RefreshMarkdownJob([
            'data' => $this->llmifyRefreshData,
        ]);

        Queue::push($job);

        $this->reset();
    }

    /**
     * @throws Exception|\yii\base\Exception
     */
    public function refreshAll(): void
    {
        $allRefreshableEntries = $this->findAllRefreshableEntries();

        foreach ($allRefreshableEntries as $entry) {
            $this->addElement($entry);
        }
    }

    /**
     * @throws Exception|\yii\base\Exception
     */
    public function refreshSection(array $sectionIds, array $siteIds): void
    {
        $allRefreshableEntries = $this->findAllRefreshableEntriesForSection($sectionIds, $siteIds);

        foreach ($allRefreshableEntries as $entry) {
            $this->addElement($entry);
        }
    }

    /**
     * @throws Exception|\yii\base\Exception
     */
    public function isRefreshAbleElement(ElementInterface $element): bool
    {
        if (!($element instanceof GlobalSet || $element instanceof Entry)) {
            return false;
        }

        if (ElementHelper::isDraftOrRevision($element)) {
            return false;
        }

        if ($element instanceof GlobalSet) {
            $this->refreshAll();
            return false;
        }

        // element is an entry
        if ($element->getOwnerId()) {
            return false;
        }

        if (!$this->canRefreshEntry($element)) {
            return false;
        }


        return true;
    }

    /**
     * @throws Exception
     */
    public function clearAll(): void
    {
        Craft::$app->getDb()->createCommand()->truncateTable(Constants::TABLE_PAGES)->execute();
    }

    /**
     * @throws Exception
     * @throws \yii\base\Exception
     */
    public function canRefreshEntry(Entry $entry): bool
    {
        $settingsService = Llmify::getInstance()->settings;

        $globalSettings = $settingsService->getGlobalSetting($entry->siteId);

        $result = false;
        if ($globalSettings->enabled) {
            $contentSettings = $settingsService->getContentSetting($entry->sectionId, $entry->siteId);
            $result = $contentSettings->enabled;
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function deleteElement(ElementInterface $element): void
    {
        $entryId = $element->id;
        $supportedSites = $element->getSupportedSites();

        foreach ($supportedSites as $supportedSite) {
            $this->deletePageByParams([
                'siteId' => $supportedSite['siteId'],
                'entryId' => $entryId,
            ]);
        }
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws \yii\base\Exception
     * @throws LoaderError
     * @throws Throwable
     */
    public function getSidebarHtml(Entry $entry): string
    {
        if (!PermissionService::canViewSidebarPanel()) {
            return '';
        }

        if (!$this->isRefreshableElement($entry)) {
            return '';
        }

        $uri = $entry->uri;
        if ($uri === null) {
            return '';
        }

        return Html::beginTag('fieldset', ['class' => 'llmify-sidebar']) .
            Html::tag('legend', 'Llmify', ['class' => 'h6']) .
            Html::tag('div', self::sidebarHtml($entry), ['class' => 'meta']) .
            Html::endTag('fieldset');
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws \yii\base\Exception
     * @throws LoaderError
     */
    private static function sidebarHtml(Entry $entry): string
    {
        $page = (new DbQuery())
        ->select([
            'dateUpdated',
            'id',
        ])
        ->from([Constants::TABLE_PAGES])
        ->where(['entryId' => $entry->id, 'siteId' => $entry->siteId])
        ->one();

        return Craft::$app->getView()->renderTemplate('llmify/widgets/sidebar', [
            'isRefreshable' => true,
            'page' => $page,
            'isEnabled' => HelperService::isMarkdownCreationEnabled(),
            'generateActionUrl' => UrlHelper::actionUrl('llmify/markdown/generate-page?entryId=' . $entry->id . '&siteId=' . $entry->siteId),
            'clearActionUrl' => UrlHelper::actionUrl('llmify/markdown/clear-page?entryId=' . $entry->id . '&siteId=' . $entry->siteId),
        ]);
    }

    /**
     * @throws Exception
     */
    private function deletePageByParams(array $params): void
    {
        (new Query())
            ->createCommand()
            ->delete(Constants::TABLE_PAGES, $params)
            ->execute();
    }

    private function findAllRefreshableEntries(): array
    {
        $allSettings = Llmify::getInstance()->settings->getAllActiveContentSettings();

        if (empty($allSettings)) {
            return [];
        }

        $sectionIds = [];
        $siteIds = [];

        foreach ($allSettings as $setting) {
            $sectionIds[] = $setting->sectionId;
            $siteIds[] = $setting->siteId;
        }

        $sectionIds = array_values(array_unique($sectionIds));
        $siteIds = array_values(array_unique($siteIds));

        return Entry::find()
            ->sectionId($sectionIds)
            ->siteId($siteIds)
            ->all();
    }

    private function findAllRefreshableEntriesForSection(array $sectionIds, array $siteIds): array
    {
        return Entry::find()
            ->sectionId($sectionIds)
            ->siteId($siteIds)
            ->all();
    }
}
