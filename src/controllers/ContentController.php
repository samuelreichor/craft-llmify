<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\ContentSettings;
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
            $section = Craft::$app->entries->getSectionById($setting->sectionId);
            if ($section) {
                $entryAmount = Entry::find()->sectionId($setting->sectionId)->status('enabled')->count();
                $settings[] = [
                    'status' => $setting->enabled,
                    'url' => UrlHelper::cpUrl("llmify/content/{$setting->sectionId}"),
                    'title' => $section->name,
                    'type' => $section->type,
                    'entries' => $entryAmount,
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

        $section = Craft::$app->entries->getSectionById($sectionId);
        $contentSettings = Llmify::getInstance()->settings;
        $helperService = Llmify::getInstance()->helper;
        $currentSiteId = $helperService->getCurrentCpSiteId();

        $sectionSettings = $contentSettings->getContentSetting($sectionId, $currentSiteId);
        $textFieldOptions = $helperService->getTextFieldsForSection($section);

        return $this->renderTemplate('llmify/settings/content/edit', [
            'section' => $section,
            'textFieldOptions' => $textFieldOptions,
            'settings' => $sectionSettings,
            'siteId' => $currentSiteId,
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
        $sectionId = $this->request->getBodyParam('sectionId');
        $siteId = $this->request->getBodyParam('siteId');

        if ($contentId) {
            $content = $settingService->getContentSetting($sectionId, $siteId);
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
        $content->sectionId = $this->request->getBodyParam('sectionId');

        if (!$settingService->saveContentSettings($content)) {
            $this->setFailFlash(Craft::t('app', 'Couldn’t save Content Setting.'));
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
