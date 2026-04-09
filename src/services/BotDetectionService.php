<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use samuelreichor\llmify\Llmify;

class BotDetectionService extends Component
{
    public const DEFAULT_BOTS = [
        'GPTBot',
        'ClaudeBot',
        'ChatGPT-User',
        'Amazonbot',
        'Bytespider',
        'CCBot',
        'Google-Extended',
        'FacebookBot',
        'PerplexityBot',
        'Applebot-Extended',
        'cohere-ai',
        'OAI-SearchBot',
        'Claude-Web',
    ];

    public function isAiBot(string $userAgent): bool
    {
        return $this->getDetectedBotName($userAgent) !== null;
    }

    public function getDetectedBotName(string $userAgent): ?string
    {
        $customBots = array_map(
            fn($row) => $row['userAgent'] ?? '',
            Llmify::getInstance()->getSettings()->additionalBotUserAgents
        );

        $allBots = array_merge(self::DEFAULT_BOTS, array_filter($customBots));

        foreach ($allBots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return $bot;
            }
        }

        return null;
    }
}
