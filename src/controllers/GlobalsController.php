<?php

namespace samuelreichor\llmify\controllers;

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
}
