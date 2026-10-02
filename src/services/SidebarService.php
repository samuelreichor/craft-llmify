<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SidebarService extends Component
{
    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws \yii\base\Exception
     * @throws LoaderError
     * @throws Throwable
     */
    public function getSidebarHtml(ElementInterface $element): string
    {
        if (!PermissionService::canViewSidebarPanel()) {
            return '';
        }

        if (!HelperService::isMarkdownCreationEnabled()) {
            return '';
        }

        if (!HelperService::isEntryOrProduct($element)) {
            return '';
        }

        if (ElementHelper::isDraftOrRevision($element)) {
            return '';
        }

        if ($element instanceof Entry && $element->getOwnerId()) {
            return '';
        }

        if ($element->uri === null) {
            return '';
        }

        $disabledReason = $this->getDisabledReason($element);

        return Html::beginTag('fieldset', ['class' => 'llmify-sidebar']) .
            Html::tag('legend', 'Llmify', ['class' => 'h6']) .
            Html::tag('div', $disabledReason !== null
                ? self::disabledSidebarHtml($disabledReason)
                : self::sidebarHtml($element), ['class' => 'meta']) .
            Html::endTag('fieldset');
    }

    /**
     * Returns the reason LLMify is disabled for this element, or null if it's active.
     *
     * @throws \yii\base\Exception
     */
    private function getDisabledReason(ElementInterface $element): ?string
    {
        $settingsService = Llmify::getInstance()->settings;
        $groupId = HelperService::getGroupIdForElement($element);

        if ($groupId === null) {
            return null;
        }

        $globalSettings = $settingsService->getGlobalSetting($element->siteId);

        if (!$globalSettings->enabled) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if ($currentUser && $currentUser->can(Constants::PERMISSION_EDIT_SITE)) {
                $site = Craft::$app->getSites()->getSiteById($element->siteId);
                $url = UrlHelper::cpUrl('llmify/globals', $site ? ['site' => $site->handle] : []);
                $label = Html::a('Site Settings', $url);
            } else {
                $label = 'Site Settings';
            }
            return 'LLMify disabled through ' . $label;
        }

        $elementType = HelperService::getElementTypeForElement($element);
        $contentSettings = $settingsService->getContentSetting($groupId, $element->siteId, $elementType);

        if (!$contentSettings->enabled) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if ($currentUser && $currentUser->can(Constants::PERMISSION_EDIT_CONTENT)) {
                $url = UrlHelper::cpUrl('llmify/content/' . $groupId, ['elementType' => $elementType]);
                $label = Html::a('Content Settings', $url);
            } else {
                $label = 'Content Settings';
            }
            return 'LLMify disabled through ' . $label;
        }

        if (HelperService::isElementExcluded($element)) {
            return 'LLMify disabled through Entry Settings';
        }

        return null;
    }

    private static function disabledSidebarHtml(string $reason): string
    {
        return '<p style="padding-block: var(--s); margin: 0; color: var(--gray-500);">' . $reason . '</p>';
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws \yii\base\Exception
     * @throws LoaderError
     * @throws Throwable
     */
    private static function sidebarHtml(ElementInterface $element): string
    {
        $cached = HelperService::getCached(Llmify::getInstance()->markdown->getPageCacheKey($element));

        return Craft::$app->getView()->renderTemplate('llmify/widgets/sidebar', [
            'cached' => $cached,
            'tokens' => $cached ? MarkdownService::estimateTokens($cached['value']) : null,
            'canGenerate' => PermissionService::canGenerate(),
            'canClear' => PermissionService::canClear(),
            'markdownUrl' => HelperService::getMarkdownUrl($element->uri, $element->siteId),
            'generateActionUrl' => UrlHelper::actionUrl('llmify/markdown/generate-page', ['elementId' => $element->id, 'siteId' => $element->siteId]),
            'clearActionUrl' => UrlHelper::actionUrl('llmify/markdown/clear-page', ['elementId' => $element->id, 'siteId' => $element->siteId]),
        ]);
    }
}
