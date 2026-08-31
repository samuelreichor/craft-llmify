<?php

namespace samuelreichor\llmify\migrations;

use craft\db\Migration;
use samuelreichor\llmify\Constants;

/**
 * m260830_000000_add_llms_toggles_and_social_links migration.
 */
class m260830_000000_add_llms_toggles_and_social_links extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Constants::TABLE_GLOBALS, 'enableLlmsTxt')) {
            $this->addColumn(
                Constants::TABLE_GLOBALS,
                'enableLlmsTxt',
                $this->boolean()->notNull()->defaultValue(true)->after('enabled')
            );
        }

        if (!$this->db->columnExists(Constants::TABLE_GLOBALS, 'enableLlmsFullTxt')) {
            $this->addColumn(
                Constants::TABLE_GLOBALS,
                'enableLlmsFullTxt',
                $this->boolean()->notNull()->defaultValue(true)->after('enableLlmsTxt')
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists(Constants::TABLE_GLOBALS, 'enableLlmsFullTxt')) {
            $this->dropColumn(Constants::TABLE_GLOBALS, 'enableLlmsFullTxt');
        }

        if ($this->db->columnExists(Constants::TABLE_GLOBALS, 'enableLlmsTxt')) {
            $this->dropColumn(Constants::TABLE_GLOBALS, 'enableLlmsTxt');
        }

        return true;
    }
}
