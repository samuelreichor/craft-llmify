<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\PermissionService;
use Throwable;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class GlobalsController extends Controller
{
    /**
     * @throws SiteNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function actionIndex(): Response
    {
        PermissionService::requireEditSiteSettings();

        $currentSiteId = Llmify::getInstance()->helper->getCurrentCpSiteId();
        $globalSettings = Llmify::getInstance()->settings;
        $settings = $globalSettings->getGlobalSetting($currentSiteId);
        $frontMatterFieldOptions = Llmify::getInstance()->helper->getFrontMatterFieldOptions();

        return $this->renderTemplate('llmify/settings/globals/index', [
            'settings' => $settings,
            'siteId' => $currentSiteId,
            'frontMatterFieldOptions' => $frontMatterFieldOptions,
        ]);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     * @throws Throwable
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();
        PermissionService::requireEditSiteSettings();

        $siteId = $this->request->getBodyParam('siteId');
        $settingService = Llmify::getInstance()->settings;
        $globalSetting = $settingService->getGlobalSetting($siteId);

        $globalSetting->enabled = $this->request->getBodyParam('enabled');
        $globalSetting->llmTitle = $this->request->getBodyParam('llmTitle');
        $globalSetting->llmDescription = $this->request->getBodyParam('llmDescription');
        $globalSetting->llmNote = $this->request->getBodyParam('llmNote');
        $globalSetting->frontMatterFields = $this->request->getBodyParam('frontMatterFields') ?? [];

        if (!$settingService->saveGlobalSettings($globalSetting)) {
            $this->setFailFlash(Craft::t('app', 'Couldn’t save Global Setting.'));
            return null;
        }

        $this->setSuccessFlash(Craft::t('app', 'Global Setting saved.'));
        return $this->redirectToPostedUrl();
    }
}
