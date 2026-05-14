<?php

namespace samuelreichor\llmify\migrations;

use craft\db\Migration;
use craft\db\Query;
use samuelreichor\llmify\Constants;

class m260514_000000_add_sort_order_to_content_settings extends Migration
{
    public function safeUp(): bool
    {
        $this->addColumn(
            Constants::TABLE_META,
            'sortOrder',
            $this->smallInteger()->unsigned()->null()->after('siteId')
        );

        // Backfill: existing rows get a sort order per site, ordered by id ASC.
        $rowsBySite = (new Query())
            ->select(['id', 'siteId'])
            ->from(Constants::TABLE_META)
            ->orderBy(['siteId' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $sortBySite = [];
        foreach ($rowsBySite as $row) {
            $siteId = (int)$row['siteId'];
            $sortBySite[$siteId] = ($sortBySite[$siteId] ?? 0) + 1;
            $this->update(
                Constants::TABLE_META,
                ['sortOrder' => $sortBySite[$siteId]],
                ['id' => $row['id']],
                [],
                false
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn(Constants::TABLE_META, 'sortOrder');
        return true;
    }
}
