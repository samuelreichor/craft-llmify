<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\ContentSettings;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ContentController extends Controller
{
    /**
     * @throws SiteNotFoundException
     */
    public function actionIndex(): Response
    {
        $sections = Craft::$app->entries->getAllSections();
        $currentSiteId = Llmify::getInstance()->helper->getCurrentCpSiteId();
        $sectionsWithUrls = array_filter($sections, function ($section) use ($currentSiteId) {
            return $section->getSiteSettings()[$currentSiteId]->uriFormat !== null;
        });

        return $this->renderTemplate('llmify/settings/content/index', [
            'sections' => $sectionsWithUrls,
        ]);
    }

    /**
     * @throws SiteNotFoundException
     * @throws Exception
     */
    public function actionEditSection(int $sectionId): Response
    {
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
     * @throws Exception
     */
    public function actionSaveSectionSettings(): ?Response
    {
        $this->requirePostRequest();
        $settingService = Llmify::getInstance()->settings;
        $contentId =  $this->request->getBodyParam('contentId');
        $sectionId =  $this->request->getBodyParam('sectionId');
        $siteId =  $this->request->getBodyParam('siteId');

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
}
