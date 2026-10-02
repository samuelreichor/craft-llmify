<?php

namespace samuelreichor\llmify\migrations;

use craft\db\Migration;
use samuelreichor\llmify\Constants;

/**
 * m261002_000000_drop_pages_table migration.
 *
 * Markdown is rendered on demand and kept in Craft's data cache, so the
 * table that stored the generated pages is no longer needed.
 */
class m261002_000000_drop_pages_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropTableIfExists(Constants::TABLE_PAGES);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m261002_000000_drop_pages_table cannot be reverted.\n";
        return false;
    }
}
