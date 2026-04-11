<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\ContentSettings;
use samuelreichor\llmify\services\HelperService;
use samuelreichor\llmify\services\PermissionService;
use Throwable;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class ContentController extends Controller
{
    /**
     * @throws SiteNotFoundException
     * @throws Throwable
     */
    public function actionIndex(): Response
    {
        PermissionService::requireEditContentSettings();

        $currentSiteId = Llmify::getInstance()->helper->getCurrentCpSiteId();
        $contentSettings = Llmify::getInstance()->settings->getContentSettingsBySiteId($currentSiteId);

        $settings = [];
        foreach ($contentSettings as $setting) {
            $groupName = null;
            $groupType = null;
            $elementCount = 0;

            if ($setting->elementType === Entry::class) {
                $section = Craft::$app->entries->getSectionById($setting->groupId);
                if ($section) {
                    $groupName = $section->name;
                    $groupType = ucfirst($section->type);
                    $elementCount = Entry::find()->sectionId($setting->groupId)->status('enabled')->count();
                }
            } elseif (HelperService::isCommerceInstalled() && $setting->elementType === \craft\commerce\elements\Product::class) {
                $productType = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeById($setting->groupId);
                if ($productType) {
                    $groupName = $productType->name;
                    $groupType = 'Product';
                    $elementCount = \craft\commerce\elements\Product::find()->typeId($setting->groupId)->status('enabled')->count();
                }
            }

            if ($groupName) {
                $settings[] = [
                    'status' => $setting->enabled,
                    'url' => UrlHelper::cpUrl("llmify/content/{$setting->groupId}", ['elementType' => $setting->elementType]),
                    'title' => $groupName,
                    'type' => $groupType,
                    'entries' => $elementCount,
                    'llmTitle' => $setting->llmTitleSource !== 'custom' || $setting->llmTitle,
                    'llmDescription' => $setting->llmDescriptionSource !== 'custom' || $setting->llmDescription,
                ];
            }
        }
        return $this->renderTemplate('llmify/settings/content/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * @throws SiteNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function actionEditSection(int $sectionId): Response
    {
        PermissionService::requireEditContentSettings();

        $elementType = Craft::$app->getRequest()->getQueryParam('elementType', Entry::class);
        $contentSettings = Llmify::getInstance()->settings;
        $currentSiteId = Llmify::getInstance()->helper->getCurrentCpSiteId();
        $fieldDiscovery = Llmify::getInstance()->fieldDiscovery;

        $sectionSettings = $contentSettings->getContentSetting($sectionId, $currentSiteId, $elementType);

        $groupName = '';
        $textFieldOptions = [];
        $frontMatterFieldOptions = [];

        if ($elementType === Entry::class) {
            $section = Craft::$app->entries->getSectionById($sectionId);
            $groupName = $section ? $section->name : '';
            $textFieldOptions = $section ? $fieldDiscovery->getSourceOptions($section) : [];
            $frontMatterFieldOptions = $section ? $fieldDiscovery->getFrontMatterOptions($section) : [];
        } elseif (HelperService::isCommerceInstalled() && $elementType === \craft\commerce\elements\Product::class) {
            $productType = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeById($sectionId);
            $groupName = $productType ? $productType->name : '';
            $textFieldOptions = $productType ? $fieldDiscovery->getSourceOptions($productType) : [];
            $frontMatterFieldOptions = $productType ? $fieldDiscovery->getFrontMatterOptions(null, null, $productType) : [];
        }

        // Get inherited front matter fields from site settings
        $globalSettings = Llmify::getInstance()->settings->getGlobalSetting($currentSiteId);
        $inheritedFrontMatterFields = $globalSettings->frontMatterFields;

        return $this->renderTemplate('llmify/settings/content/edit', [
            'groupName' => $groupName,
            'elementType' => $elementType,
            'textFieldOptions' => $textFieldOptions,
            'frontMatterFieldOptions' => $frontMatterFieldOptions,
            'settings' => $sectionSettings,
            'siteId' => $currentSiteId,
            'inheritedFrontMatterFields' => $inheritedFrontMatterFields,
        ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     * @throws Exception|\yii\base\Exception
     * @throws Throwable
     */
    public function actionSaveSectionSettings(): ?Response
    {
        PermissionService::requireEditContentSettings();

        $this->requirePostRequest();
        $settingService = Llmify::getInstance()->settings;
        $contentId = $this->request->getBodyParam('contentId');
        $groupId = $this->request->getBodyParam('groupId');
        $siteId = $this->request->getBodyParam('siteId');
        $elementType = $this->request->getBodyParam('elementType', Entry::class);

        if ($contentId) {
            $content = $settingService->getContentSetting($groupId, $siteId, $elementType);
        } else {
            $content = new ContentSettings();
            $content->siteId = $siteId;
        }

        $content->enabled = $this->request->getBodyParam('enabled');
        $content->llmTitleSource = $this->request->getBodyParam('llmTitleSource');
        $content->llmTitle = $this->request->getBodyParam('llmTitle');
        $content->llmDescription = $this->request->getBodyParam('llmDescription');
        $content->llmDescriptionSource = $this->request->getBodyParam('llmDescriptionSource');
        $content->llmSectionTitle = $this->request->getBodyParam('llmSectionTitle');
        $content->llmSectionDescription = $this->request->getBodyParam('llmSectionDescription');
        $content->overrideFrontMatter = (bool)$this->request->getBodyParam('overrideFrontMatter');
        $content->frontMatterFields = $this->request->getBodyParam('frontMatterFields') ?? [];
        $content->groupId = $groupId;
        $content->elementType = $elementType;

        if (!$settingService->saveContentSettings($content)) {
            $this->setFailFlash(Craft::t('app', "Couldn't save Content Setting."));
            return null;
        }

        $this->setSuccessFlash(Craft::t('app', 'Content Setting saved.'));
        return $this->redirectToPostedUrl();
    }

    public function actionRedirect(): Response
    {
        // As there is no dashboard we simply redirect to content
        $targetUrl = UrlHelper::cpUrl('llmify/content');
        return $this->redirect($targetUrl);
    }
}
