<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use yii\web\Response;

class SettingsController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionIndex(): Response
    {
        $this->requireAdmin();

        return $this->renderTemplate('llmify/settings/index', [
            'plugin' => Llmify::getInstance(),
            'settings' => Llmify::getInstance()->getSettings(),
            'readOnly' => !Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ]);
    }

    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $plugin = Llmify::getInstance();
        $posted = $this->request->getBodyParam('settings', []);

        $settings = array_merge($plugin->getSettings()->toArray(), $posted);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            Craft::$app->getSession()->setError('Couldn\'t save plugin settings.');

            return null;
        }

        Craft::$app->getSession()->setNotice('Plugin settings saved.');

        return $this->redirectToPostedUrl();
    }
}
