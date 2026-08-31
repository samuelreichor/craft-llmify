<?php

namespace samuelreichor\llmify\migrations;

use craft\db\Migration;
use samuelreichor\llmify\Constants;
use yii\db\Expression;

/**
 * m260831_100000_add_uri_to_pages migration.
 */
class m260831_100000_add_uri_to_pages extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Constants::TABLE_PAGES, 'uri')) {
            $this->addColumn(
                Constants::TABLE_PAGES,
                'uri',
                $this->string()->after('elementType')
            );
        }

        // Backfill from the uri already stored in the elementMeta JSON column
        if ($this->db->getIsMysql()) {
            $uriExpression = new Expression("JSON_UNQUOTE(JSON_EXTRACT([[elementMeta]], '$.uri'))");
        } else {
            $uriExpression = new Expression("[[elementMeta]]->>'uri'");
        }

        $this->update(
            Constants::TABLE_PAGES,
            ['uri' => $uriExpression],
            ['not', ['elementMeta' => null]],
            [],
            false
        );

        $this->createIndex(null, Constants::TABLE_PAGES, ['siteId', 'uri']);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists(Constants::TABLE_PAGES, 'uri')) {
            $this->dropColumn(Constants::TABLE_PAGES, 'uri');
        }

        return true;
    }
}
