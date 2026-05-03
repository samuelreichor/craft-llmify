<?php

namespace samuelreichor\llmify\console\controllers;

use craft\console\Controller;
use samuelreichor\llmify\services\BotDetectionService;
use yii\console\ExitCode;

/**
 * Manages the bundled list of known AI bot user agents.
 */
class BotsController extends Controller
{
    public string $source = 'https://raw.githubusercontent.com/ai-robots-txt/ai.robots.txt/main/robots.json';

    public $defaultAction = 'sync';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['source']);
    }

    /**
     * llmify/bots/sync — refresh src/data/ai-bots.json from ai-robots-txt.
     */
    public function actionSync(): int
    {
        $this->stdout("Fetching AI bot list from {$this->source}\n");

        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => "User-Agent: craft-llmify bots-sync\r\n",
            ],
        ]);

        $raw = @file_get_contents($this->source, false, $context);

        if ($raw === false) {
            $this->stderr("Failed to fetch source.\n");
            return ExitCode::IOERR;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === []) {
            $this->stderr("Invalid JSON payload.\n");
            return ExitCode::DATAERR;
        }

        $written = file_put_contents(
            BotDetectionService::DATA_FILE,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($written === false) {
            $this->stderr("Failed to write " . BotDetectionService::DATA_FILE . "\n");
            return ExitCode::CANTCREAT;
        }

        $this->stdout(sprintf("Synced %d bots.\n", count($decoded)));
        return ExitCode::OK;
    }
}
