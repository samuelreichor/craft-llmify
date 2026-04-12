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
        if ($elementChanged !== null) {
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
        if ($this->llmifyRefreshData->isEmpty()) {
            return;
        }

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
        $allRefreshableElements = $this->findAllRefreshableElements();

        foreach ($allRefreshableElements as $element) {
            $this->addElement($element);
        }
    }

    /**
     * @throws Exception|\yii\base\Exception
     */
    public function refreshGroup(array $groupIds, array $siteIds, string $elementType = Entry::class): void
    {
        $allRefreshableElements = $this->findAllRefreshableElementsForGroup($groupIds, $siteIds, $elementType);

        foreach ($allRefreshableElements as $element) {
            $this->addElement($element);
        }
    }

    /**
     * @throws Exception|\yii\base\Exception
     */
    public function refreshPagesByGroups(array $groupIds, array $siteIds, string $elementType = Entry::class): void
    {
        $elementIds = PageRecord::find()
            ->where(['groupId' => $groupIds, 'siteId' => $siteIds, 'elementType' => $elementType])
            ->select('elementId')
            ->column();

        if (empty($elementIds)) {
            return;
        }

        if ($elementType === Entry::class) {
            $refreshableElements = Entry::find()->id($elementIds)->sectionId($groupIds)->siteId($siteIds)->all();
        } elseif (HelperService::isCommerceInstalled() && $elementType === \craft\commerce\elements\Product::class) {
            $refreshableElements = \craft\commerce\elements\Product::find()->id($elementIds)->typeId($groupIds)->siteId($siteIds)->all();
        } else {
            return;
        }

        foreach ($refreshableElements as $element) {
            $this->addElement($element);
        }
    }

    /**
     * @throws Exception|\yii\base\Exception
     */
    public function isRefreshAbleElement(ElementInterface $element): bool
    {
        if ($element instanceof GlobalSet) {
            $this->refreshAll();
            return false;
        }

        if (!($element instanceof Entry) && !$this->isCommerceProduct($element)) {
            return false;
        }

        if (ElementHelper::isDraftOrRevision($element)) {
            return false;
        }

        // element is an entry or product
        if ($element instanceof Entry && $element->getOwnerId()) {
            return false;
        }

        if (!$this->canRefreshElement($element)) {
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
    public function canRefreshElement(ElementInterface $element): bool
    {
        $settingsService = Llmify::getInstance()->settings;
        $groupId = HelperService::getGroupIdForElement($element);
        $elementType = HelperService::getElementTypeForElement($element);

        if ($groupId === null) {
            return false;
        }

        $globalSettings = $settingsService->getGlobalSetting($element->siteId);

        $result = false;
        if ($globalSettings->enabled) {
            $contentSettings = $settingsService->getContentSetting($groupId, $element->siteId, $elementType);
            $result = $contentSettings->enabled;
        }

        if (!$result) {
            return false;
        }

        return !HelperService::isElementExcluded($element);
    }

    /**
     * @throws Exception
     */
    public function deleteElement(ElementInterface $element): void
    {
        $elementId = $element->id;
        $supportedSites = $element->getSupportedSites();

        foreach ($supportedSites as $supportedSite) {
            $this->deletePageByParams([
                'siteId' => $supportedSite['siteId'],
                'elementId' => $elementId,
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
    public function getSidebarHtml(ElementInterface $element): string
    {
        if (!PermissionService::canViewSidebarPanel()) {
            return '';
        }

        // Main plugin switch — hide sidebar completely
        if (!HelperService::isMarkdownCreationEnabled()) {
            return '';
        }

        // Structural checks — element must be entry/product, not draft, not owned, have URI
        if (!($element instanceof Entry) && !$this->isCommerceProduct($element)) {
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
     */
    private static function sidebarHtml(ElementInterface $element): string
    {
        $page = (new DbQuery())
        ->select([
            'dateUpdated',
            'id',
            'content',
        ])
        ->from([Constants::TABLE_PAGES])
        ->where(['elementId' => $element->id, 'siteId' => $element->siteId])
        ->one();

        return Craft::$app->getView()->renderTemplate('llmify/widgets/sidebar', [
            'isRefreshable' => true,
            'page' => $page,
            'canGenerate' => PermissionService::canGenerate(),
            'canClear' => PermissionService::canClear(),
            'markdownUrl' => HelperService::getMarkdownUrl($element->uri, $element->siteId),
            'generateActionUrl' => UrlHelper::actionUrl('llmify/markdown/generate-page?elementId=' . $element->id . '&siteId=' . $element->siteId),
            'clearActionUrl' => UrlHelper::actionUrl('llmify/markdown/clear-page?elementId=' . $element->id . '&siteId=' . $element->siteId),
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

    private function findAllRefreshableElements(): array
    {
        $allSettings = Llmify::getInstance()->settings->getAllActiveContentSettings();

        if (empty($allSettings)) {
            return [];
        }

        $entrySectionIds = [];
        $entrySiteIds = [];
        $productTypeIds = [];
        $productSiteIds = [];

        foreach ($allSettings as $setting) {
            if ($setting->elementType === Entry::class) {
                $entrySectionIds[] = $setting->groupId;
                $entrySiteIds[] = $setting->siteId;
            } elseif (HelperService::isCommerceInstalled() && $setting->elementType === \craft\commerce\elements\Product::class) {
                $productTypeIds[] = $setting->groupId;
                $productSiteIds[] = $setting->siteId;
            }
        }

        $elements = [];

        if (!empty($entrySectionIds)) {
            $entrySectionIds = array_values(array_unique($entrySectionIds));
            $entrySiteIds = array_values(array_unique($entrySiteIds));
            $elements = array_merge($elements, Entry::find()->sectionId($entrySectionIds)->siteId($entrySiteIds)->all());
        }

        if (!empty($productTypeIds) && HelperService::isCommerceInstalled()) {
            $productTypeIds = array_values(array_unique($productTypeIds));
            $productSiteIds = array_values(array_unique($productSiteIds));
            $elements = array_merge($elements, \craft\commerce\elements\Product::find()->typeId($productTypeIds)->siteId($productSiteIds)->all());
        }

        return $elements;
    }

    private function findAllRefreshableElementsForGroup(array $groupIds, array $siteIds, string $elementType = Entry::class): array
    {
        if ($elementType === Entry::class) {
            return Entry::find()->sectionId($groupIds)->siteId($siteIds)->all();
        }

        if (HelperService::isCommerceInstalled() && $elementType === \craft\commerce\elements\Product::class) {
            return \craft\commerce\elements\Product::find()->typeId($groupIds)->siteId($siteIds)->all();
        }

        return [];
    }

    private function isCommerceProduct(ElementInterface $element): bool
    {
        return HelperService::isCommerceInstalled()
            && $element instanceof \craft\commerce\elements\Product;
    }
}
