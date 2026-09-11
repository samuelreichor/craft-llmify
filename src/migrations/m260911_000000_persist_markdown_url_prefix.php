<?php

namespace samuelreichor\llmify\migrations;

use Craft;
use craft\db\Migration;

/**
 * m260911_000000_persist_markdown_url_prefix migration.
 *
 * The default `markdownUrlPrefix` changed from `raw` to an empty string so new
 * installs follow the llms.txt spec (markdown at the original URL + `.md`).
 * Existing installs that never stored the setting keep their `raw` URLs.
 */
class m260911_000000_persist_markdown_url_prefix extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.llmify.schemaVersion', true);

        if (version_compare($schemaVersion, '1.4.0', '<')) {
            if ($projectConfig->get('plugins.llmify.settings.markdownUrlPrefix', true) === null) {
                $projectConfig->set(
                    'plugins.llmify.settings.markdownUrlPrefix',
                    'raw',
                    'Persist the previous default markdown URL prefix for LLMify'
                );
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260911_000000_persist_markdown_url_prefix cannot be reverted.\n";
        return false;
    }
}
