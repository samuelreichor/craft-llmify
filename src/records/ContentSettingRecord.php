<?php

namespace samuelreichor\llmify\records;

use craft\db\ActiveRecord;
use samuelreichor\llmify\Constants;

/**
 * Content Setting Record
 * @property int $id
 * @property string $llmTitleSource
 * @property string $llmTitle
 * @property string $llmDescriptionSource
 * @property string $llmDescription
 * @property string $llmSectionTitle
 * @property string $llmSectionDescription
 * @property string|null $frontMatterFields
 * @property bool $overrideFrontMatter
 * @property int $sectionId
 * @property int $entryTypeId
 * @property int $siteId
 * @property bool $enabled
 */
class ContentSettingRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_META;
    }
}
