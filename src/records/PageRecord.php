<?php

namespace samuelreichor\llmify\records;

use craft\db\ActiveRecord;
use samuelreichor\llmify\Constants;

/**
 * Page Record
 *
 * @property int $id
 * @property int $siteId
 * @property int $sectionId
 * @property int $entryId
 * @property int $metadataId
 * @property string $content
 * @property string $title
 * @property string $description
 */
class PageRecord extends ActiveRecord
{
    public static function tableName()
    {
        return Constants::TABLE_PAGES;
    }
}
