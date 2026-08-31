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
     * Checks if a plugin is installed
     *
     * @param string $pluginHandle
     * @return bool
     */
    public static function isPluginInstalledAndEnabled(string $pluginHandle): bool
    {
        $plugin = Craft::$app->plugins->getPlugin($pluginHandle);

        if ($plugin !== null && $plugin->isInstalled) {
            return true;
        }

        return false;
    }

    /**
     * Checks if Craft Commerce is installed and enabled
     */
    public static function isCommerceInstalled(): bool
    {
        return self::isPluginInstalledAndEnabled('commerce');
    }

    /**
     * Returns SEOmatic's "Same As URLs" for a site as rows of
     * `['siteName' => ..., 'url' => ...]`, skipping entries without a URL.
     * Empty array when SEOmatic is not installed.
     */
    public static function getSeomaticSocialLinks(int $siteId): array
    {
        if (!self::isPluginInstalledAndEnabled('seomatic')) {
            return [];
        }

        $sameAsLinks = [];
        try {
            $metaBundle = \nystudio107\seomatic\Seomatic::$plugin->metaBundles->getGlobalMetaBundle($siteId);
            $sameAsLinks = $metaBundle?->metaSiteVars->sameAsLinks ?? [];
        } catch (\Throwable $e) {
            Craft::warning('Could not load SEOmatic sameAs links: ' . $e->getMessage(), 'llmify');
        }

        // SEOmatic stores an empty string when the "Same As URLs" table has no rows.
        if (!is_array($sameAsLinks)) {
            $sameAsLinks = [];
        }

        $links = [];
        foreach ($sameAsLinks as $link) {
            if (!is_array($link)) {
                continue;
            }

            $siteName = trim((string)($link['siteName'] ?? ''));
            $url = trim((string)($link['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $links[] = ['siteName' => $siteName, 'url' => $url];
        }

        return $links;
    }

    /**
     * Normalizes posted social links rows. Returns an empty array when the
     * rows still match SEOmatic's "Same As URLs" preset, so the table keeps
     * following SEOmatic until the user actually changes something.
     */
    public static function normalizeSocialLinks(mixed $rows, int $siteId): array
    {
        if (!is_array($rows)) {
            $rows = [];
        }

        $links = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $siteName = trim($row['siteName'] ?? '');
            $url = trim($row['url'] ?? '');

            if ($siteName === '' && $url === '') {
                continue;
            }

            $links[] = ['siteName' => $siteName, 'url' => $url];
        }

        if ($links === self::getSeomaticSocialLinks($siteId)) {
            return [];
        }

        return $links;
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
