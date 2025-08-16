<?php

namespace samuelreichor\llmify\records;

use craft\db\ActiveRecord;
use samuelreichor\llmify\Constants;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * Page Record
 *
 * @property int $entryId
 * @property int $siteId
 * @property int $sectionId
 * @property int $metadataId
 * @property string $content
 * @property string $title
 * @property string $description
 * @property array $entryMeta
 */
class PageRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_PAGES;
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'dateCreated',
                'updatedAtAttribute' => 'dateUpdated',
                'value' => new Expression('NOW()'),
            ],
        ];
    }
}
