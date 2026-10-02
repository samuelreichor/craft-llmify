<?php

namespace samuelreichor\llmify\migrations;

use craft\db\Migration;
use samuelreichor\llmify\Constants;

/**
 * m261002_100000_drop_llms_full_txt migration.
 */
class m261002_100000_drop_llms_full_txt extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists(Constants::TABLE_GLOBALS, 'enableLlmsFullTxt')) {
            $this->dropColumn(Constants::TABLE_GLOBALS, 'enableLlmsFullTxt');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m261002_100000_drop_llms_full_txt cannot be reverted.\n";
        return false;
    }
}
