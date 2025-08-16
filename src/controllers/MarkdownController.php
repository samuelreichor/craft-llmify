<?php

namespace samuelreichor\llmify\controllers;

use Craft;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craft\web\Controller;
use samuelreichor\llmify\Llmify;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class MarkdownController extends Controller
{
    /**
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws \yii\base\Exception
     */
    public function actionGenerate(): Response
    {
        $this->requirePostRequest();

        Llmify::getInstance()->refresh->refreshAll();
        $this->setSuccessFlash('Markdown generation started. Jobs have been added to the queue.');
        return $this->redirectToPostedUrl();
    }

    /**
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function actionClear(): Response
    {
        $this->requirePostRequest();

        Llmify::getInstance()->refresh->clearAll();
        $this->setSuccessFlash('All markdowns successfully removed.');

        return $this->redirectToPostedUrl();
    }

    /**
     * @throws MethodNotAllowedHttpException
     * @throws ElementNotFoundException
     */
    public function actionGeneratePage(int $entryId, int $siteId): Response
    {
        $this->requirePostRequest();
        $entry = Entry::find()->id($entryId)->siteId($siteId)->one();

        if (!$entry) {
            throw new ElementNotFoundException();
        }

        try {
            Llmify::getInstance()->refresh->addElement($entry);
        } catch (Exception|\yii\base\Exception $e) {
            Craft::error($e->getMessage(), 'llmify');
            return $this->asJson(['success' => false, 'message' => 'An error occurred while updating the Markdown.']);
        }

        return $this->asJson([
            'success' => true,
            'message' => 'Markdown successfully updated.',
        ]);
    }


    /**
     * @throws MethodNotAllowedHttpException
     * @throws ElementNotFoundException
     */
    public function actionClearPage(int $entryId, int $siteId): Response
    {
        $this->requirePostRequest();
        $entry = Entry::find()->id($entryId)->siteId($siteId)->one();

        if (!$entry) {
            throw new ElementNotFoundException();
        }

        try {
            Llmify::getInstance()->refresh->deleteElement($entry);
        } catch (Exception $e) {
            Craft::error($e->getMessage(), 'llmify');
            return $this->asJson(['success' => false, 'message' => 'An error occurred while clearing the Markdown.']);
        }

        return $this->asJson([
            'success' => true,
            'message' => 'Markdown successfully cleared.',
        ]);
    }
}
