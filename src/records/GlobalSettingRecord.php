<?php

namespace samuelreichor\llmify\records;

use Craft;
use craft\db\ActiveRecord;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\models\ContentSettings;

/**
 * Content Setting Record
 * @property int $siteId
 * @property bool $enabled
 * @property string $llmTitle
 * @property string $llmDescription
 */
class GlobalSettingRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_GLOBALS;
    }
}
