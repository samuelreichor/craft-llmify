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

    /**
     * Whether the group (section or product type) referenced by a content setting
     * still has URLs in the given site. Returns false if the group was deleted
     * or no longer has URLs in that site, in which case its content settings
     * should be hidden from the CP, but the DB row is kept.
     */
    public static function groupHasUrlsInSite(int $groupId, int $siteId, string $elementType): bool
    {
        if ($elementType === Entry::class) {
            $section = Craft::$app->entries->getSectionById($groupId);
            return ($section?->getSiteSettings()[$siteId] ?? null)?->hasUrls ?? false;
        }

        if (self::isCommerceInstalled() && $elementType === \craft\commerce\elements\Product::class) {
            $productType = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeById($groupId);
            return ($productType?->getSiteSettings()[$siteId] ?? null)?->hasUrls ?? false;
        }

        return false;
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
     * Render a user-supplied string as a Twig object template. Supports the
     * shorthand `{name}` syntax that Craft uses for URI/title formats and
     * Generated Fields. Plain strings without `{` skip Twig but still pass
     * through the strip/trim sanitisation when `$stripTags` is true.
     *
     * Failures are logged and the original template is returned so a broken
     * snippet in a CP setting can never crash the public llms.txt output.
     */
    public static function renderTwig(?string $template, mixed $object = null, bool $stripTags = true): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        if (!str_contains($template, '{')) {
            return $stripTags ? trim(strip_tags($template)) : $template;
        }

        try {
            $rendered = Craft::$app->getView()->renderObjectTemplate($template, $object ?? new \stdClass());
        } catch (\Throwable $e) {
            Craft::warning('LLMify Twig render failed: ' . $e->getMessage(), 'llmify');
            $rendered = $template;
        }

        return $stripTags ? trim(strip_tags($rendered)) : $rendered;
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

    /**
     * Path-only counterpart to `getMarkdownUrl()` — returns `/raw/news/news-1-2.md`
     * rather than an absolute URL. Useful for consumers (e.g. analytics) that
     * key off paths and don't want to re-parse a site URL.
     *
     * @throws Exception
     */
    public static function getMarkdownPath(string $uri, ?int $siteId = null): string
    {
        return (string)parse_url(self::getMarkdownUrl($uri, $siteId), PHP_URL_PATH);
    }
}
