<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use samuelreichor\llmify\jobs\RefreshMarkdownJob;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\RefreshData;
use yii\db\Exception;

class RefreshService extends Component
{
    public RefreshData $refreshData;

    public function init(): void
    {
        parent::init();
        $this->reset();
    }

    public function reset(): void
    {
        $this->refreshData = new RefreshData();
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

        $this->refreshData->addSiteId($element->siteId);
        $this->refreshData->addUrl($element->getUrl());
        $this->refreshData->addElementId($element->id, $element::class);
    }

    public function refresh(): void
    {
        if ($this->refreshData->isEmpty()) {
            return;
        }

        $job = new RefreshMarkdownJob([
            'data' => $this->refreshData,
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
    public function isRefreshAbleElement(ElementInterface $element): bool
    {
        /*Todo: This logic does not work with categories or other elements.*/
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

        if ($element instanceof Entry) {
            if ($element->getOwnerId()) {
                return false;
            }

            if (!$this->canRefreshEntry($element)) {
                return false;
            }
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
            $contentSettings = $settingsService->getContentSetting($entry->sectionId, $entry->siteId);
            $result = $contentSettings->enabled;
        }

        return $result;
    }

    private function findRelatedEntries(ElementInterface $element): array
    {
        if (!$element->id) {
            return [];
        }

        return Entry::find()
            ->relatedTo($element)
            ->siteId($element->siteId)
            ->all();
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
}
