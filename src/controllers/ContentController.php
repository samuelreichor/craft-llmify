<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use Craft;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use yii\web\Response;

class ContentController extends Controller
{
    public function actionIndex(): Response
    {
        $sections = Craft::$app->sections->getAllSections();

        $sectionsWithUrls = array_filter($sections, function ($section) {
            return $section->getUriFormat() !== null;
        });

        return $this->renderTemplate('llmify/settings/content/index', [
            'sections' => $sectionsWithUrls,
        ]);
    }

    public function actionEditSection(int $sectionId): Response
    {
        $section = Craft::$app->sections->getSectionById($sectionId);

        if (!$section) {
            throw new \yii\web\NotFoundHttpException('Section not found.');
        }

        $entryTypes = $section->getEntryTypes();
        $commonTextFields = [];

        if (!empty($entryTypes)) {
            $allFields = [];
            foreach ($entryTypes as $entryType) {
                $fields = $entryType->getCustomFields();
                $textFields = array_filter($fields, function ($field) {
                    return $field instanceof \craft\fields\PlainText;
                });
                $allFields[] = array_map(function ($field) {
                    return $field->handle;
                }, $textFields);
            }

            if (!empty($allFields)) {
                $commonFieldHandles = array_intersect(...$allFields);
                foreach ($commonFieldHandles as $handle) {
                    $field = Craft::$app->fields->getFieldByHandle($handle);
                    if ($field) {
                        $commonTextFields[] = [
                            'label' => $field->name,
                            'value' => $field->handle,
                        ];
                    }
                }
            }
        }

        $settings = Llmify::getInstance()->getSettings();
        $sectionSettings = $settings->contentSettings[$sectionId] ?? [];

        return $this->renderTemplate('llmify/settings/content/edit', [
            'section' => $section,
            'textFields' => $commonTextFields,
            'settings' => $sectionSettings,
        ]);
    }

    public function actionSaveSectionSettings(): ?Response
    {
        $this->requirePostRequest();
        $sectionId = Craft::$app->getRequest()->getRequiredBodyParam('sectionId');
        $sectionSettings = Craft::$app->getRequest()->getBodyParam('settings');

        $settings = Llmify::getInstance()->getSettings();
        $settings->contentSettings[$sectionId] = $sectionSettings;

        Craft::$app->plugins->savePluginSettings(Llmify::getInstance(), $settings->toArray());

        Craft::$app->session->setNotice('Settings saved.');
        return $this->redirectToPostedUrl();
    }
}
