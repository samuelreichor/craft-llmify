<?php

namespace samuelreichor\llmify\behaviors;

use Craft;
use craft\base\Element;
use craft\helpers\ElementHelper;
use yii\base\Behavior;
use yii\base\Exception;

/**
 * Element Changed Behavior behavior
 */
class ElementChangedBehavior extends Behavior
{
    /**
     * @const string
     */
    public const BEHAVIOR_NAME = 'llmify_element_changed';
    /**
     * @var Element|null
     */
    public ?Element $originalElement = null;

    /**
     * @var array<int,bool> The original element’s site statuses
     */
    public array $originalElementSiteStatuses = [];

    /**
     * @var string[] The attributes that changed.
     */
    public array $changedAttributes = [];

    /**
     * @var string[] The field handles of the custom fields that changed.
     */
    public array $changedFieldsHandles = [];

    /**
     * @var bool Whether the element has changed
     */
    public bool $hasChanged = false;

    /**
     * @inerhitdoc
     */
    public function attach($owner): void
    {
        parent::attach($owner);

        $element = $this->owner;

        // Don't proceed if this is a new element
        if (!$element->id === null) {
            return;
        }

        $this->originalElement = Craft::$app->getElements()->getElementById($element->id, $element::class, $element->siteId);

        if ($this->originalElement !== null) {
            $this->originalElementSiteStatuses = ElementHelper::siteStatusesForElement($this->originalElement);
        }
    }

    /**
     * Returns whether the element has changed.
     * @throws Exception
     */
    public function getHasChanged(): bool
    {
        $element = $this->owner;

        $this->changedAttributes = $this->getChangedAttributes();
        $this->changedFieldsHandles = $this->getChangedFieldHandles();

        if ($element->firstSave) {
            return true;
        }

        if ($this->getHasBeenDeleted()) {
            return true;
        }

        if ($this->getHasStatusChanged()) {
            return true;
        }

        if (!empty($this->changedAttributes) || !empty($this->changedFieldsHandles)) {
            $this->hasChanged = true;

            return true;
        }

        return false;
    }

    /**
     * Returns whether the element has been deleted.
     */
    public function getHasBeenDeleted(): bool
    {
        $element = $this->owner;

        return $element->dateDeleted !== null;
    }

    /**
     * Returns whether the element’s status or any site statuses have changed.
     * @throws Exception
     */
    public function getHasStatusChanged(): bool
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

            if (
                $siteStatus !== null
                && $originalSiteStatus !== null
                && $siteStatus !== $originalSiteStatus
            ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Returns the attributes that have changed.
     */
    private function getChangedAttributes(): array
    {
        $element = $this->owner;

        if ($element->duplicateOf === null) {
            $changedAttributes = $element->getDirtyAttributes();
        } else {
            $changedAttributes = $element->duplicateOf->getModifiedAttributes();
        }

        return $changedAttributes;
    }

    /**
     * Returns the field handles of the custom fields that have changed.
     *
     * @return string[]
     */
    private function getChangedFieldHandles(): array
    {
        $element = $this->owner;

        if ($element->duplicateOf === null) {
            $changedFieldHandles = $element->getDirtyFields();
        } else {
            $changedFieldHandles = $element->duplicateOf->getModifiedFields();
        }

        return $changedFieldHandles;
    }
}
