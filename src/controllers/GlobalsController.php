<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use yii\web\Response;

class GlobalsController extends Controller
{
    public function actionIndex(): Response
    {
        $settings = Llmify::getInstance()->getSettings();

        return $this->renderTemplate('llmify/settings/globals/index', [
            'settings' => $settings,
        ]);
    }

    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();
        $settings = Craft::$app->getRequest()->getBodyParam('settings');
        
        Craft::$app->plugins->savePluginSettings(Llmify::getInstance(), $settings);

        Craft::$app->session->setNotice('Settings saved.');
        return $this->redirectToPostedUrl();
    }
}
