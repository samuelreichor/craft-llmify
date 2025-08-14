<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\GlobalSettings;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class GlobalsController extends Controller
{
    /**
     * @throws SiteNotFoundException
     * @throws Exception
     */
    public function actionIndex(): Response
    {
        $currentSiteId = Llmify::getInstance()->helper->getCurrentCpSiteId();
        $globalSettings = Llmify::getInstance()->settings;
        $settings = $globalSettings->getGlobalSetting($currentSiteId);

        return $this->renderTemplate('llmify/settings/globals/index', [
            'settings' => $settings,
            'siteId' => $currentSiteId,
        ]);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        $siteId = $this->request->getBodyParam('siteId');
        $settingService = Llmify::getInstance()->settings;
        $globalSetting = $settingService->getGlobalSetting($siteId);

        $globalSetting->enabled = $this->request->getBodyParam('enabled');
        $globalSetting->llmTitle = $this->request->getBodyParam('llmTitle');
        $globalSetting->llmDescription = $this->request->getBodyParam('llmDescription');
        $globalSetting->llmNote = $this->request->getBodyParam('llmNote');

        if (!$settingService->saveGlobalSettings($globalSetting)) {
            $this->setFailFlash(Craft::t('app', 'Couldn’t save Global Setting.'));
            return null;
        }

        $this->setSuccessFlash(Craft::t('app', 'Global Setting saved.'));
        return $this->redirectToPostedUrl();
    }

    public function actionRedirect(): Response
    {
        // As there is no dashboard we simply redirect to globals
        $targetUrl = UrlHelper::cpUrl('llmify/globals');
        return $this->redirect($targetUrl);
    }
}
