<?php

namespace samuelreichor\llmify\migrations;

use craft\db\Migration;
use samuelreichor\llmify\Constants;

/**
 * m260831_000000_add_social_links_to_globals migration.
 */
class m260831_000000_add_social_links_to_globals extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Constants::TABLE_GLOBALS, 'socialLinks')) {
            $this->addColumn(
                Constants::TABLE_GLOBALS,
                'socialLinks',
                $this->text()->after('includeSocialLinks')
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists(Constants::TABLE_GLOBALS, 'socialLinks')) {
            $this->dropColumn(Constants::TABLE_GLOBALS, 'socialLinks');
        }

        return true;
    }
}
