<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\models\ContentSettings;
use samuelreichor\llmify\models\GlobalSettings;
use samuelreichor\llmify\records\ContentSettingRecord;
use samuelreichor\llmify\records\GlobalSettingRecord;
use yii\db\Exception;
use craft\db\Query as DbQuery;

class SettingsService extends Component
{
    private array $contentSettings = [];
    private array $allContentSettings = [];
    private array $globalSettings = [];
    /**
     * @throws Exception
     */
    public function saveContentSettings(ContentSettings $contentSettings, bool $runValidation = true): bool
    {
        $isNewsSetting = !$contentSettings->id;

        if ($runValidation && !$contentSettings->validate()) {
            Craft::info('Content settings not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewsSetting) {
            $contentRecord = new ContentSettingRecord();
        } else {
            $contentRecord = ContentSettingRecord::findOne($contentSettings->id);
            if (!$contentRecord) {
                $contentRecord = new ContentSettingRecord();
            }
        }

        $contentRecord->enabled = $contentSettings->enabled;
        $contentRecord->siteId = $contentSettings->siteId;
        $contentRecord->llmTitleSource = $contentSettings->llmTitleSource;
        $contentRecord->llmTitle = $contentSettings->llmTitle;
        $contentRecord->llmDescription = $contentSettings->llmDescription;
        $contentRecord->llmDescriptionSource = $contentSettings->llmDescriptionSource;
        $contentRecord->sectionId = $contentSettings->sectionId;

        $contentRecord->save();

        if ($isNewsSetting) {
            $contentSettings->id = $contentRecord->id;
        }
        return true;
    }

    /**
     * @throws Exception
     */
    public function getContentSetting(int $sectionId, int $siteId): ContentSettings
    {
        $cacheKey = $this->createContentCacheKey($sectionId, $siteId);

        if (isset($this->contentSettings[$cacheKey])) {
            return $this->contentSettings[$cacheKey];
        }

        $result = $this->_createContentMetaQuery()
            ->where(['sectionId' => $sectionId, 'siteId' => $siteId])
            ->one();

        $settings = $result ? new ContentSettings($result) : null;

        if (!$settings) {
            $settings = new ContentSettings();
            $settings->sectionId = $sectionId;
            $settings->siteId = $siteId;
            $this->saveContentSettings($settings);
        }

        $this->contentSettings[$cacheKey] = $settings;
        return $settings;
    }

    public function getAllContentSettings(int $siteId): array
    {
        if (isset($this->allContentSettings[$siteId])) {
            return $this->allContentSettings[$siteId];
        }

        $results = $this->_createContentMetaQuery()
            ->where(['siteId' => $siteId])
            ->all();

        foreach ($results as $result) {
            $settings = new ContentSettings($result);
            $this->allContentSettings[$siteId][] = $settings;

            $cacheKey = $this->createContentCacheKey($settings->sectionId, $settings->siteId);
            $this->contentSettings[$cacheKey] = $settings;
        }

        return $this->allContentSettings[$siteId];
    }

    /**
     * @throws Exception
     */
    public function setContentSetting($sectionId, $siteId): void
    {
        $this->getContentSetting($sectionId, $siteId);
    }

    /**
     * @throws Exception
     */
    public function delContentSetting(int $sectionId, int $siteId): void
    {
        Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_META, ['sectionId' => $sectionId, 'siteId' => $siteId])
            ->execute();
    }

    /**
     * @throws Exception
     */
    public function setAllContentSettings(): void
    {
        $allSiteIds = Craft::$app->getSites()->getAllSiteIds();
        $allSectionIds = Craft::$app->entries->getAllSectionIds();

        foreach ($allSiteIds as $siteId) {
            foreach ($allSectionIds as $sectionId) {
                $this->setContentSetting($sectionId, $siteId);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function saveGlobalSettings(GlobalSettings $globalSettings, bool $runValidation = true): bool
    {
        if ($runValidation && !$globalSettings->validate()) {
            Craft::info('Global settings not saved due to validation error.', __METHOD__);
            return false;
        }

        $globalRecord = GlobalSettingRecord::findOne($globalSettings->siteId) ?: new GlobalSettingRecord();

        if (!$globalRecord) {
            $globalRecord = new GlobalSettingRecord();
        }

        $globalRecord->siteId = $globalSettings->siteId;
        $globalRecord->enabled = $globalSettings->enabled;
        $globalRecord->llmTitle = $globalSettings->llmTitle;
        $globalRecord->llmDescription = $globalSettings->llmDescription;

        $globalRecord->save();
        return true;
    }

    /**
     * @throws Exception
     */
    public function getGlobalSetting(int $siteId): GlobalSettings
    {
        if (isset($this->globalSettings[$siteId])) {
            return $this->globalSettings[$siteId];
        }

        $result = $this->_createGlobalSettingsQuery()
            ->where(['siteId' => $siteId])
            ->one();

        $settings = $result ? new GlobalSettings($result) : null;

        if (!$settings) {
            $settings = new GlobalSettings();
            $settings->siteId = $siteId;
            $this->saveGlobalSettings($settings);
        }

        $this->globalSettings[$siteId] = $settings;
        return $settings;
    }

    /**
     * @throws Exception
     */
    public function setGlobalSetting($siteId): void
    {
        $this->getGlobalSetting($siteId);
    }

    /**
     * @throws Exception
     */
    public function delGlobalSetting(int $siteId): void
    {
        Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_GLOBALS, ['siteId' => $siteId])
            ->execute();
    }

    /**
     * @throws Exception
     */
    public function setAllGlobalSettings(): void
    {
        $allSiteIds = Craft::$app->getSites()->getAllSiteIds();
        foreach ($allSiteIds as $siteId) {
            $this->setGlobalSetting($siteId);
        }
    }

    private function _createContentMetaQuery(): DbQuery
    {
        return (new DbQuery())
            ->select([
                'id',
                'enabled',
                'llmTitleSource',
                'llmTitle',
                'llmDescriptionSource',
                'llmDescription',
                'sectionId',
                'siteId',
            ])
            ->from([Constants::TABLE_META]);
    }

    private function _createGlobalSettingsQuery(): DbQuery
    {
        return (new DbQuery())
            ->select([
                'siteId',
                'llmTitle',
                'llmDescription',
                'enabled',
            ])
            ->from([Constants::TABLE_GLOBALS]);
    }

    private function createContentCacheKey(string $sectionId, string $siteId): string
    {
        return  $sectionId . '-' . $siteId;
    }
}
