<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use samuelreichor\llmify\Llmify;

class BotDetectionService extends Component
{
    public const DATA_FILE = __DIR__ . '/../data/ai-bots.json';

    private ?array $_aiBots = null;

    public function isAiBot(string $userAgent): bool
    {
        return $this->getDetectedBotName($userAgent) !== null;
    }

    public function getDetectedBotName(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        foreach ($this->getKnownBots() as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return $bot;
            }
        }

        $customBots = array_filter(array_map(
            fn($row) => $row['userAgent'] ?? '',
            Llmify::getInstance()->getSettings()->additionalBotUserAgents
        ));

        foreach ($customBots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return $bot;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getKnownBots(): array
    {
        if ($this->_aiBots !== null) {
            return $this->_aiBots;
        }

        $this->_aiBots = [];

        if (!is_file(self::DATA_FILE)) {
            return $this->_aiBots;
        }

        $raw = file_get_contents(self::DATA_FILE);
        if ($raw === false) {
            return $this->_aiBots;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->_aiBots;
        }

        $this->_aiBots = array_keys($decoded);
        return $this->_aiBots;
    }
}
