<?php

namespace samuelreichor\llmify\migrations;

use Craft;
use craft\db\Migration;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use yii\base\Exception;
use yii\db\Exception as DbException;

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
            $this->insertDefaultData();
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
                    'id' => $this->primaryKey(),
                    'entryId' => $this->integer()->notNull(),
                    'siteId' => $this->integer(),
                    'sectionId' => $this->integer(),
                    'entryMeta' => $this->json(),
                    'metadataId' => $this->integer(),
                    'title' => $this->string(),
                    'description' => $this->string(),
                    'content' => $this->longText(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
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
                    'enabled' => $this->boolean()->notNull(),
                    'siteId' => $this->integer(),
                    'sectionId' => $this->integer(),
                    'entryTypeId' => $this->integer(),
                    'llmTitleSource' => $this->string(),
                    'llmTitle' => $this->string(),
                    'llmDescriptionSource' => $this->string(),
                    'llmDescription' => $this->string(),
                    'llmSectionTitle' => $this->string(),
                    'llmSectionDescription' => $this->string(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                ]
            );
        }

        $tableMeta = Craft::$app->db->schema->getTableSchema(Constants::TABLE_GLOBALS);
        if ($tableMeta === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_GLOBALS,
                [
                    'siteId' => $this->integer()->notNull(),
                    'enabled' => $this->boolean()->notNull(),
                    'llmTitle' => $this->string(),
                    'llmDescription' => $this->string(),
                    'llmNote' => $this->string(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'PRIMARY KEY([[siteId]])',
                ]
            );
        }

        return $tablesCreated;
    }

    protected function removeTables(): void
    {
        $this->dropTableIfExists(Constants::TABLE_PAGES);
        $this->dropTableIfExists(Constants::TABLE_META);
        $this->dropTableIfExists(Constants::TABLE_GLOBALS);
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

        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            Constants::TABLE_GLOBALS,
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            null
        );
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    protected function insertDefaultData(): void
    {
        Llmify::getInstance()->settings->setAllContentSettings();
        Llmify::getInstance()->settings->setAllGlobalSettings();
    }
}
