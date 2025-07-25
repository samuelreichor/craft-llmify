<?php

namespace samuelreichor\llmify\migrations;

use Craft;
use craft\db\Migration;
use samuelreichor\llmify\Constants;
use yii\base\Exception;

class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public string $driver;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function createTables(): bool
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_PAGES);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_PAGES,
                [
                    'id' => $this->primaryKey(),
                    'url' => $this->string(),
                    'siteId' => $this->integer(),
                    'content' => $this->json(),
                    'title' => $this->string(),
                    'description' => $this->string(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                ]
            );
        }

        return $tablesCreated;
    }

    protected function removeTables(): void
    {
        $this->dropTableIfExists(Constants::TABLE_PAGES);
    }

    protected function addForeignKeys(): void
    {
    }
}
