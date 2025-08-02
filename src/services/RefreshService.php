<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\helpers\ElementHelper;
use craft\db\Query as DbQuery;
use samuelreichor\llmify\jobs\RefreshMarkdown;
use samuelreichor\llmify\Llmify;
use yii\db\Exception;

class RefreshService extends Component
{
    /**
     * @throws Exception
     */
    public function addElement(ElementInterface $element): void
    {
        if (!$this->isRefreshableElement($element)) {
            return;
        }

        $entriesToRefresh = [];

        if ($element instanceof Entry) {
            $entriesToRefresh[] = $element->id;
        }

        $relatedEntryIds = $this->_findRelatedEntries($element);
        $entriesToRefresh = array_merge($entriesToRefresh, $relatedEntryIds);

        if ($element instanceof GlobalSet) {
            $allEntries = $this->_findAllRefreshableEntries();
            $entriesToRefresh = array_merge($entriesToRefresh, $allEntries);
        }

        $uniqueEntries = [];
        $processedIds = [];
        foreach ($entriesToRefresh as $entry) {
            if (!in_array($entry->id, $processedIds, true)) {
                $uniqueEntries[] = $entry;
                $processedIds[] = $entry->id;
            }
        }

        foreach ($uniqueEntries as $entry) {
            Craft::$app->getQueue()->push(new RefreshMarkdown([
                'entryId' => $entry->id,
            ]));
        }
    }

    public function refreshAll(): void
    {
        dd('refreshAll');
    }

    /**
     * @throws Exception
     */
    public function isRefreshAbleElement(ElementInterface $element): bool
    {

        if (!($element instanceof Element) || $element instanceof Asset) {
            return false;
        }

        if (ElementHelper::isDraftOrRevision($element)) {
            return false;
        }

        if ($element->propagating) {
            return false;
        }

        if ($element instanceof GlobalSet) {
            $this->refreshAll();
            return false;
        }

        if ($element instanceof Entry && !$this->canRefreshEntry($element)) {
            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function canRefreshEntry(Entry $entry): bool
    {
        $settingsService = Llmify::getInstance()->settings;

        $globalSettings = $settingsService->getGlobalSetting($entry->siteId);

        $result = false;
        if ($globalSettings->enabled) {
            $contentSettings = $settingsService->getContentSetting($entry->section->id, $entry->site->id);
            $result = $contentSettings->enabled;
        }

        return $result;
    }

    private function _findRelatedEntries(ElementInterface $element): array
    {
        if (!$element->id) {
            return [];
        }

        return Entry::find()
            ->relatedTo($element)
            ->siteId($element->siteId)
            ->ids();
    }

    private function _findAllRefreshableEntries(): array
    {
        $settingsService = Llmify::getInstance()->settings;
        $contentSettings = $settingsService->getAndSetAllContentSettings();

        $sectionIds = array_keys(array_filter($contentSettings, function ($setting) {
            return $setting->enabled;
        }));

        if (empty($sectionIds)) {
            return [];
        }

        return Entry::find()
            ->sectionId($sectionIds)
            ->all();
    }
}
