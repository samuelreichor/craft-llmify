<?php

namespace samuelreichor\llmify\migrations;

use craft\db\Migration;
use samuelreichor\llmify\Constants;

/**
 * Promotes description/note columns from VARCHAR(255) to TEXT so editors
 * can paste longer LLM descriptions without hitting the MySQL length limit.
 */
class m260516_000000_widen_description_columns extends Migration
{
    private const COLUMNS = [
        Constants::TABLE_META => ['llmDescription', 'llmSectionDescription'],
        Constants::TABLE_GLOBALS => ['llmDescription', 'llmNote'],
        Constants::TABLE_PAGES => ['description'],
    ];

    public function safeUp(): bool
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->db->columnExists($table, $column)) {
                    $this->alterColumn($table, $column, $this->text());
                }
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->db->columnExists($table, $column)) {
                    $this->alterColumn($table, $column, $this->string());
                }
            }
        }

        return true;
    }
}
