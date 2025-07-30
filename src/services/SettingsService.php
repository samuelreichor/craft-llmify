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

    public function getContentSettingBySectionIdSiteId(int $sectionId, int $siteId): ?ContentSettings
    {
        $result = $this->_createContentMetaQuery()
            ->where(['sectionId' => $sectionId, 'siteId' => $siteId])
            ->one();

        return $result ? new ContentSettings($result) : null;
    }

    /**
     * @throws Exception
     */
    public function getAndSetContentSettings(Entry $entry): ContentSettings
    {
        $sectionId = $entry->section->id;
        $siteId = $entry->site->id;
        $cacheKey = $sectionId . '-' . $siteId;

        if (isset($this->contentSettings[$cacheKey])) {
            return $this->contentSettings[$cacheKey];
        }

        $settings = $this->getContentSettingBySectionIdSiteId($sectionId, $siteId);

        if (!$settings) {
            $settings = new ContentSettings();
            $settings->sectionId = $sectionId;
            $settings->siteId = $siteId;
            $this->saveContentSettings($settings);
        }

        $this->contentSettings[$cacheKey] = $settings;
        return $settings;
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

    public function getGlobalSettingsBySiteId(int $siteId): ?GlobalSettings
    {
        $result = $this->_createGlobalSettingsQuery()
            ->where(['siteId' => $siteId])
            ->one();

        return $result ? new GlobalSettings($result) : null;
    }

    /**
     * @throws Exception
     */
    public function getAndSetGlobalSettings(int $siteId): GlobalSettings
    {
        if (isset($this->globalSettings[$siteId])) {
            return $this->globalSettings[$siteId];
        }

        $settings = $this->getGlobalSettingsBySiteId($siteId);

        if (!$settings) {
            $settings = new GlobalSettings();
            $settings->siteId = $siteId;
            $this->saveGlobalSettings($settings);
        }

        $this->globalSettings[$siteId] = $settings;
        return $settings;
    }

    private function _createContentMetaQuery(): DbQuery
    {
        return (new DbQuery())
            ->select([
                'id',
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
}
