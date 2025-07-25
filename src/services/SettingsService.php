<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\models\ContentSettings;
use samuelreichor\llmify\records\ContentSettingRecord;
use yii\db\Exception;
use craft\db\Query as DbQuery;

class SettingsService extends Component
{
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
            $contentRecord = ContentSettingRecord::findOne($contentSettings->id) ?: new ContentSettingRecord();
        }

        $contentRecord->llmTitleSource = $contentSettings->llmTitleSource;
        $contentRecord->llmTitle = $contentSettings->llmTitle;
        $contentRecord->llmDescription = $contentSettings->llmDescription;
        $contentRecord->llmDescriptionSource = $contentSettings->llmDescriptionSource;
        $contentRecord->sectionId = $contentSettings->sectionId;

        $contentRecord->save();
        $contentRecord->id = $contentSettings->id;

        return true;
    }

    public function getContentSettingBySectionId(int $sectionId): ?ContentSettings
    {
        $result = $this->_createContentMetaQuery()
            ->where(['sectionId' => $sectionId])
            ->one();

        return $result ? new ContentSettings($result) : null;
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
            ])
            ->from([Constants::TABLE_META]);
    }
}
