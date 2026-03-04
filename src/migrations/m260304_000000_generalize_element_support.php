<?php

namespace samuelreichor\llmify\migrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\services\HelperService;

class m260304_000000_generalize_element_support extends Migration
{
    public function safeUp(): bool
    {
        // --- llmify_pages ---

        // Drop FK on sectionId (references sections table)
        $this->dropForeignKeyForColumn(Constants::TABLE_PAGES, 'sectionId');

        // Rename columns
        $this->renameColumn(Constants::TABLE_PAGES, 'entryId', 'elementId');
        $this->renameColumn(Constants::TABLE_PAGES, 'sectionId', 'groupId');
        $this->renameColumn(Constants::TABLE_PAGES, 'entryMeta', 'elementMeta');

        // Add elementType column and set default via UPDATE to avoid MySQL backslash escaping in DEFAULT clause
        $this->addColumn(
            Constants::TABLE_PAGES,
            'elementType',
            $this->string()->notNull()->defaultValue('')->after('elementId')
        );
        $this->update(Constants::TABLE_PAGES, ['elementType' => Entry::class]);

        // --- llmify_metadata ---

        // Rename column
        $this->renameColumn(Constants::TABLE_META, 'sectionId', 'groupId');

        // Add elementType column and set default via UPDATE
        $this->addColumn(
            Constants::TABLE_META,
            'elementType',
            $this->string()->notNull()->defaultValue('')->after('groupId')
        );
        $this->update(Constants::TABLE_META, ['elementType' => Entry::class]);

        // Create metadata rows for Commerce product types (if Commerce is installed)
        $this->insertCommerceProductTypeSettings();

        return true;
    }

    public function safeDown(): bool
    {
        // --- llmify_pages ---

        // Remove elementType column
        $this->dropColumn(Constants::TABLE_PAGES, 'elementType');

        // Rename columns back
        $this->renameColumn(Constants::TABLE_PAGES, 'elementId', 'entryId');
        $this->renameColumn(Constants::TABLE_PAGES, 'groupId', 'sectionId');
        $this->renameColumn(Constants::TABLE_PAGES, 'elementMeta', 'entryMeta');

        // Restore FK on sectionId
        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            Constants::TABLE_PAGES,
            'sectionId',
            '{{%sections}}',
            'id',
            'CASCADE',
            null
        );

        // --- llmify_metadata ---

        // Remove elementType column
        $this->dropColumn(Constants::TABLE_META, 'elementType');

        // Rename column back
        $this->renameColumn(Constants::TABLE_META, 'groupId', 'sectionId');

        return true;
    }

    private function insertCommerceProductTypeSettings(): void
    {
        if (!HelperService::isCommerceInstalled()) {
            return;
        }

        $allSiteIds = Craft::$app->getSites()->getAllSiteIds();
        $productTypes = \craft\commerce\Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $productClass = \craft\commerce\elements\Product::class;
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        foreach ($allSiteIds as $siteId) {
            foreach ($productTypes as $productType) {
                // Check if a row already exists
                $exists = (new \craft\db\Query())
                    ->from(Constants::TABLE_META)
                    ->where(['groupId' => $productType->id, 'siteId' => $siteId, 'elementType' => $productClass])
                    ->exists();

                if (!$exists) {
                    $this->insert(Constants::TABLE_META, [
                        'enabled' => false,
                        'siteId' => $siteId,
                        'groupId' => $productType->id,
                        'elementType' => $productClass,
                        'llmTitleSource' => 'title',
                        'llmDescriptionSource' => 'custom',
                        'dateCreated' => $now,
                        'dateUpdated' => $now,
                    ]);
                }
            }
        }
    }

    private function dropForeignKeyForColumn(string $table, string $column): void
    {
        $foreignKeys = $this->db->getSchema()->getTableSchema($table)?->foreignKeys ?? [];

        foreach ($foreignKeys as $fkName => $fk) {
            // The FK array has index 0 as the referenced table, remaining are column mappings
            $fkColumns = array_keys(array_slice($fk, 1));
            if (in_array($column, $fkColumns, true)) {
                $this->dropForeignKey($fkName, $table);
                return;
            }
        }
    }
}
