<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\db\Query as DbQuery;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\ContentSettings;
use samuelreichor\llmify\models\GlobalSettings;
use samuelreichor\llmify\records\ContentSettingRecord;
use samuelreichor\llmify\records\GlobalSettingRecord;
use yii\db\Exception;

class SettingsService extends Component
{
    private array $contentSettings = [];
    private array $allEnabledContentSettings = [];
    private array $contentSettingsBySiteId = [];
    private array $globalSettings = [];
    private array $allEnabledSiteIds = [];

    /**
     * @throws Exception
     * @throws \yii\base\Exception
     */
    public function saveContentSettings(ContentSettings $contentSettings, bool $runValidation = true, bool $triggerRefresh = true): bool
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
        $contentRecord->llmSectionTitle = $contentSettings->llmSectionTitle;
        $contentRecord->llmSectionDescription = $contentSettings->llmSectionDescription;
        $contentRecord->sectionId = $contentSettings->sectionId;

        $contentRecord->save();

        // on installation no refresh is needed
        if ($triggerRefresh) {
            Llmify::getInstance()->refresh->refreshPagesBySections(
                [$contentSettings->sectionId],
                [$contentSettings->siteId]
            );
        }

        if ($isNewsSetting) {
            $contentSettings->id = $contentRecord->id;
        }
        return true;
    }

    /**
     * @throws Exception|\yii\base\Exception
     */
    public function getContentSetting(int $sectionId, int $siteId, bool $triggerRefresh = true): ContentSettings
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
            $this->saveContentSettings($settings, true, $triggerRefresh);
        }

        $this->contentSettings[$cacheKey] = $settings;
        return $settings;
    }

    public function getAllActiveContentSettings(): array
    {
        if ($this->allEnabledContentSettings) {
            return $this->allEnabledContentSettings;
        }

        foreach ($this->getAllActiveGlobalSettingsIds() as $siteId) {
            $results = $this->_createContentMetaQuery()
                ->where(['siteId' => $siteId, 'enabled' => true])
                ->all();

            foreach ($results as $result) {
                $settings = new ContentSettings($result);
                $this->allEnabledContentSettings[] = $settings;

                $cacheKey = $this->createContentCacheKey($settings->sectionId, $settings->siteId);
                $this->contentSettings[$cacheKey] = $settings;
            }
        }

        return $this->allEnabledContentSettings;
    }

    public function getContentSettingsBySiteId(int $siteId): array
    {
        if (isset($this->contentSettingsBySiteId[$siteId])) {
            return $this->contentSettingsBySiteId[$siteId];
        }

        $results = $this->_createContentMetaQuery()
            ->where(['siteId' => $siteId])
            ->all();

        foreach ($results as $result) {
            $settings = new ContentSettings($result);
            $this->contentSettingsBySiteId[$siteId][] = $settings;
            $cacheKey = $this->createContentCacheKey($settings->sectionId, $settings->siteId);
            $this->contentSettings[$cacheKey] = $settings;
        }

        return $this->contentSettingsBySiteId[$siteId];
    }

    /**
     * @throws Exception
     * @throws \yii\base\Exception
     */
    public function setContentSetting(int $sectionId, int $siteId, bool $triggerRefresh = true): void
    {
        $this->getContentSetting($sectionId, $siteId, $triggerRefresh);
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
     * @throws \yii\base\Exception
     */
    public function setAllContentSettings(): void
    {
        $allSiteIds = Craft::$app->getSites()->getAllSiteIds();

        $allSection = Craft::$app->entries->getAllSections();
        $sectionIdsWithUrls = [];
        foreach ($allSection as $section) {
            foreach ($section->getSiteSettings() as $siteSetting) {
                if ($siteSetting->hasUrls) {
                    $sectionIdsWithUrls[] = $section->id;
                    break;
                }
            }
        }

        foreach ($allSiteIds as $siteId) {
            foreach ($sectionIdsWithUrls as $sectionId) {
                $this->setContentSetting($sectionId, $siteId, false);
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
        $globalRecord->siteId = $globalSettings->siteId;
        $globalRecord->enabled = $globalSettings->enabled;
        $globalRecord->llmTitle = $globalSettings->llmTitle;
        $globalRecord->llmDescription = $globalSettings->llmDescription;
        $globalRecord->llmNote = $globalSettings->llmNote;

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

    public function getAllActiveGlobalSettingsIds(): array
    {
        if ($this->allEnabledSiteIds) {
            return $this->allEnabledSiteIds;
        }

        $results = $this->_createGlobalSettingsQuery()
            ->where(['enabled' => true])
            ->all();

        foreach ($results as $result) {
            $settings = new GlobalSettings($result);
            $this->globalSettings[$settings->siteId] = $settings;
            $this->allEnabledSiteIds[] = $settings->siteId;
        }

        return $this->allEnabledSiteIds;
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
                'llmSectionTitle',
                'llmSectionDescription',
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
                'llmNote',
                'enabled',
            ])
            ->from([Constants::TABLE_GLOBALS]);
    }

    private function createContentCacheKey(string $sectionId, string $siteId): string
    {
        return  $sectionId . '-' . $siteId;
    }
}
