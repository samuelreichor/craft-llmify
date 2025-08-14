<?php

namespace samuelreichor\llmify\records;

use craft\db\ActiveRecord;
use samuelreichor\llmify\Constants;

/**
 * Content Setting Record
 * @property int $siteId
 * @property bool $enabled
 * @property string $llmTitle
 * @property string $llmDescription
 * @property string $llmNote
 */
class GlobalSettingRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_GLOBALS;
    }
}
