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

        $tablePages = Craft::$app->db->schema->getTableSchema(Constants::TABLE_PAGES);
        if ($tablePages === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_PAGES,
                [
                    'entryId' => $this->integer()->notNull(),
                    'siteId' => $this->integer(),
                    'sectionId' => $this->integer(),
                    'metadataId' => $this->integer(),
                    'title' => $this->string(),
                    'description' => $this->string(),
                    'content' => $this->json(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'PRIMARY KEY([[entryId]])',
                ]
            );
        }

        $tableMeta = Craft::$app->db->schema->getTableSchema(Constants::TABLE_META);
        if ($tableMeta === null) {
            $tablesCreated = true;
            $this->createTable(
            Constants::TABLE_META,
                [
                    'id' => $this->primaryKey(),
                    'sectionId' => $this->integer(),
                    'llmTitleSource' => $this->string(),
                    'llmTitle' => $this->string(),
                    'llmDescriptionSource' => $this->string(),
                    'llmDescription' => $this->string(),
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
        $this->dropTableIfExists(Constants::TABLE_META);
    }

    protected function addForeignKeys(): void
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            Constants::TABLE_PAGES,
            'entryId',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            Constants::TABLE_PAGES,
            'sectionId',
            '{{%sections}}',
            'id',
            'CASCADE',
            null
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            Constants::TABLE_PAGES,
            'metadataId',
            Constants::TABLE_META,
            'id',
            'CASCADE',
            null
        );
    }
}
