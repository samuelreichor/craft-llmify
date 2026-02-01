<?php

namespace samuelreichor\llmify\migrations;

use craft\db\Migration;
use samuelreichor\llmify\Constants;

/**
 * m250201_000000_add_front_matter_to_settings migration.
 */
class m250201_000000_add_front_matter_to_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add frontMatterFields to llmify_globals table
        if (!$this->db->columnExists(Constants::TABLE_GLOBALS, 'frontMatterFields')) {
            $this->addColumn(
                Constants::TABLE_GLOBALS,
                'frontMatterFields',
                $this->text()->after('llmNote')
            );
        }

        // Add frontMatterFields and overrideFrontMatter to llmify_metadata table
        if (!$this->db->columnExists(Constants::TABLE_META, 'frontMatterFields')) {
            $this->addColumn(
                Constants::TABLE_META,
                'frontMatterFields',
                $this->text()->after('llmSectionDescription')
            );
        }

        if (!$this->db->columnExists(Constants::TABLE_META, 'overrideFrontMatter')) {
            $this->addColumn(
                Constants::TABLE_META,
                'overrideFrontMatter',
                $this->boolean()->defaultValue(false)->after('frontMatterFields')
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Remove columns from llmify_globals
        if ($this->db->columnExists(Constants::TABLE_GLOBALS, 'frontMatterFields')) {
            $this->dropColumn(Constants::TABLE_GLOBALS, 'frontMatterFields');
        }

        // Remove columns from llmify_metadata
        if ($this->db->columnExists(Constants::TABLE_META, 'overrideFrontMatter')) {
            $this->dropColumn(Constants::TABLE_META, 'overrideFrontMatter');
        }

        if ($this->db->columnExists(Constants::TABLE_META, 'frontMatterFields')) {
            $this->dropColumn(Constants::TABLE_META, 'frontMatterFields');
        }

        return true;
    }
}
