<?php

namespace samuelreichor\llmify\migrations;

use craft\db\Migration;
use samuelreichor\llmify\Constants;

/**
 * m261002_000000_drop_pages_table migration.
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
