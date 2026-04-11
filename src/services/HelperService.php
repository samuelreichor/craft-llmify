<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\errors\InvalidFieldException;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use samuelreichor\llmify\fields\LlmifySettingsField;
use samuelreichor\llmify\Llmify;
use yii\base\Exception;

class HelperService extends Component
{
    /**
     * @throws SiteNotFoundException
     */
    public function getCurrentCpSiteId(): ?int
    {
        $site = null;
        $siteHandle = Craft::$app->getRequest()->getQueryParam('site');

        $sites = Craft::$app->getSites();
        if ($siteHandle) {
            $site = $sites->getSiteByHandle($siteHandle);
        }

        if (!$site) {
            $site = $sites->getCurrentSite();
        }

        return $site->id;
    }

    /**
     * Checks if Craft Commerce is installed and enabled
     */
    public static function isCommerceInstalled(): bool
    {
        $plugin = Craft::$app->plugins->getPlugin('commerce');

        if ($plugin !== null && $plugin->isInstalled) {
            return true;
        }

        return false;
    }

    /**
     * Get the groupId for an element (sectionId for entries, productTypeId for products)
     */
    public static function getGroupIdForElement(ElementInterface $element): ?int
    {
        if ($element instanceof Entry) {
            return $element->sectionId;
        }

        if (self::isCommerceInstalled() && $element instanceof \craft\commerce\elements\Product) {
            return $element->typeId;
        }

        return null;
    }

    /**
     * Get the element type class for an element
     */
    public static function getElementTypeForElement(ElementInterface $element): string
    {
        return $element::class;
    }

    public function getFieldOfTypeFromElement(ElementInterface $element, string $fieldClass): ?FieldInterface
    {
        $layout = $element->getFieldLayout();

        if (!$layout) {
            return null;
        }

        $fields = $layout->getCustomFields();
        foreach ($fields as $field) {
            if ($field instanceof $fieldClass) {
                return $field;
            }
        }

        return null;
    }

    public static function isMarkdownCreationEnabled(): bool
    {
        return Llmify::getInstance()->getSettings()->isEnabled;
    }

    /**
     * Check if an element is excluded from LLMify via the LlmifySettingsField.
     * @throws InvalidFieldException
     */
    public static function isElementExcluded(ElementInterface $element): bool
    {
        $field = Llmify::getInstance()->helper->getFieldOfTypeFromElement($element, LlmifySettingsField::class);
        if (!$field) {
            return false;
        }

        $fieldData = $element->getFieldValue($field->handle);
        if (!is_array($fieldData)) {
            return false;
        }

        // Lightswitch stores '1' when on, '' when off
        // Default to enabled (not excluded) when the key doesn't exist
        if (!array_key_exists('enabled', $fieldData)) {
            return false;
        }

        return empty($fieldData['enabled']);
    }

    /**
     * @throws Exception
     */
    public static function getMarkdownUrl(string $uri, ?int $siteId = null): string
    {
        $mdPrefix = Llmify::getInstance()->getSettings()->markdownUrlPrefix;
        if ($mdPrefix !== '') {
            return UrlHelper::siteUrl("{$mdPrefix}/{$uri}.md", null, null, $siteId);
        }

        return UrlHelper::siteUrl("{$uri}.md", null, null, $siteId);
    }
}
