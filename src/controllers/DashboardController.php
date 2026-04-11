<?php

namespace samuelreichor\llmify\controllers;

use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\PermissionService;
use yii\web\Response;

class DashboardController extends Controller
{
    public function actionIndex(): Response
    {
        PermissionService::requireViewDashboard();

        $dashboard = Llmify::getInstance()->dashboard;
        $currentSiteId = Llmify::getInstance()->helper->getCurrentCpSiteId();

        return $this->renderTemplate('llmify/settings/dashboard/index', [
            'currentSiteId' => $currentSiteId,
            'pluginChecks' => $dashboard->getPluginSettingsChecks(),
            'siteSetup' => $dashboard->getSiteSetupData($currentSiteId),
            'contentSetup' => $dashboard->getContentSetupData($currentSiteId),
            'generationStats' => $dashboard->getGenerationStats($currentSiteId),
        ]);
    }
}
