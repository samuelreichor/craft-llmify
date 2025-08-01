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
use samuelreichor\llmify\Constants;
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

        // check if element has changed
        // check what else may have changes (other entries)
        // push those elements to the queue to generate markdowns

        dd('add to queue');
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

        $globalSettings = $settingsService->getAndSetGlobalSettings($entry->siteId);

        $result = false;
        if ($globalSettings->enabled) {
            $contentSettings = $settingsService->getAndSetContentSettings($entry);
            $result = $contentSettings->enabled;
        }

        return $result;
    }
}
