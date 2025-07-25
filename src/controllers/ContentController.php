<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\fields\PlainText;
use craft\models\Section;
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
        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;
        $sectionsWithUrls = array_filter($sections, function ($section) use ($currentSiteId) {
            return $section->getSiteSettings()[$currentSiteId]->uriFormat !== null;
        });

        return $this->renderTemplate('llmify/settings/content/index', [
            'sections' => $sectionsWithUrls,
        ]);
    }

    public function actionEditSection(int $sectionId): Response
    {
        $section = Craft::$app->entries->getSectionById($sectionId);
        $contentSettings = Llmify::getInstance()->settings;

        $sectionSettings = $contentSettings->getContentSettingBySectionId($sectionId);
        if (!$sectionSettings) {
            $sectionSettings = new ContentSettings();
        }

        $commonTextFields = $this->getFieldsForSection($section);

        return $this->renderTemplate('llmify/settings/content/edit', [
            'section' => $section,
            'textFields' => $commonTextFields,
            'settings' => $sectionSettings,
        ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionSaveSectionSettings(): ?Response
    {
        $this->requirePostRequest();
        $settingService = Llmify::getInstance()->settings;
        $contentId =  $this->request->getBodyParam('contentId');
        $sectionId =  $this->request->getBodyParam('sectionId');

        if ($contentId) {
            $content = $settingService->getContentSettingBySectionId($sectionId);

            if (!$content) {
                throw new NotFoundHttpException('Content not found');
            }
        } else {
            $content = new ContentSettings();
        }

        $content->llmTitleSource = $this->request->getBodyParam('llmTitleSource');
        $content->llmTitle = $this->request->getBodyParam('llmTitle');
        $content->llmDescription = $this->request->getBodyParam('llmDescription');
        $content->llmDescriptionSource = $this->request->getBodyParam('llmDescriptionSource');
        $content->sectionId = $this->request->getBodyParam('sectionId');

        if (!$settingService->saveContentSettings($content)) {
            $this->setFailFlash(Craft::t('app', 'Couldn’t save Content Setting.'));
            return null;
        }

        $this->setSuccessFlash(Craft::t('app', 'Content Setting saved.'));
        return $this->redirectToPostedUrl($content);
    }

    public function getFieldsForSection(Section $section): array
    {
        $entryTypes = $section->getEntryTypes();
        $commonTextFields = [];

        if (!empty($entryTypes)) {
            $allFields = [];
            foreach ($entryTypes as $entryType) {
                $fields = $entryType->getCustomFields();
                $textFields = array_filter($fields, function ($field) {
                    return $field instanceof PlainText;
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
        return $commonTextFields;
    }
}
