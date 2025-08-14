<?php

namespace samuelreichor\llmify\behaviors;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use samuelreichor\llmify\Llmify;
use yii\base\Behavior;
use yii\base\Exception;

/**
 * Element Changed behavior
 *
 * @property Entry $owner
 */
class ElementChangedBehavior extends Behavior
{
    public const BEHAVIOR_NAME = 'elementChanged';
    public ?Element $originalElement = null;
    public array $originalElementSiteStatuses = [];
    /**
     * @inerhitdoc
     */
    public function attach($owner): void
    {
        parent::attach($owner);

        $element = $this->owner;

        // Only existing elements can change.
        if ($element->id === null) {
            return;
        }

        $this->originalElement = Craft::$app->getElements()->getElementById($element->id, $element::class, $element->siteId);

        if ($this->originalElement !== null) {
            $this->originalElementSiteStatuses = ElementHelper::siteStatusesForElement($this->originalElement);
        }
    }

    /**
     * @throws Exception
     */
    public function hasChanged(): bool
    {
        $element = $this->owner;

        if ($element->firstSave) {
            return true;
        }

        if (!$this->hasPage()) {
            return true;
        }

        if ($this->hasBeenDeleted()) {
            return true;
        }

        if ($this->hasStatusChanged()) {
            return true;
        }

        if ($this->hasDirtyFields()) {
            return true;
        }

        return false;
    }

    public function hasDirtyFields(): bool
    {
        $element = $this->owner;

        if ($element->duplicateOf === null) {
            if (!empty($element->getDirtyAttributes()) || !empty($element->getDirtyFields())) {
                return true;
            }
        } else {
            if (!empty($element->duplicateOf->getModifiedAttributes()) || !empty($element->duplicateOf->getModifiedFields())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function hasStatusChanged(): bool
    {
        $element = $this->owner;

        if ($this->originalElement === null) {
            return false;
        }

        if ($element->getStatus() != $this->originalElement->getStatus()) {
            return true;
        }

        $supportedSites = ElementHelper::supportedSitesForElement($element);

        foreach ($supportedSites as $supportedSite) {
            $siteId = $supportedSite['siteId'];
            $siteStatus = $element->getEnabledForSite($siteId);
            $originalSiteStatus = $this->originalElementSiteStatuses[$siteId] ?? null;

            if ($siteStatus !== null && $originalSiteStatus !== null && $siteStatus !== $originalSiteStatus) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function hasRefreshableStatus(): bool
    {
        $element = $this->owner;
        $elementStatus = $element->getStatus();
        $refreshableStatuses = [
            'live',
            'active',
            'pending',
            'expired',
        ];

        return in_array($elementStatus, $refreshableStatuses);
    }

    public function hasBeenDeleted(): bool
    {
        $element = $this->owner;

        return $element->dateDeleted !== null;
    }

    public function hasPage(): bool
    {
        $element = $this->owner;
        $page = Llmify::getInstance()->markdown->getMarkdown($element->uri, $element->siteId);
        return $page !== null;
    }
}
