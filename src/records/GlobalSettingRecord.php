<?php

namespace samuelreichor\llmify\records;

use craft\db\ActiveRecord;
use samuelreichor\llmify\Constants;

/**
 * Global Setting Record
 * @property int $siteId
 * @property bool $enabled
 * @property bool $enableLlmsTxt
 * @property bool $enableLlmsFullTxt
 * @property string $llmTitle
 * @property string $llmDescription
 * @property string $llmNote
 * @property bool $includeSocialLinks
 * @property string|null $socialLinks
 * @property string|null $frontMatterFields
 */
class GlobalSettingRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_GLOBALS;
    }
}
