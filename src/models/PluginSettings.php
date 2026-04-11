<?php

namespace samuelreichor\llmify\models;

use craft\base\Model;

class PluginSettings extends Model
{
    public bool $isEnabled = true;
    public bool $isRealUrlLlm = false;
    public bool $autoServeMarkdown = true;
    public string $markdownUrlPrefix = 'raw';
    public int $concurrentRequests = 3;
    public int $requestTimeout = 100;
    public array $markdownConfig = [
        'strip_tags' => true,
        'header_style' => 'atx',
        'remove_nodes' => 'img picture style form button input select option svg script nav noscript',
    ];
    public array $excludeClasses = [
        [
            'classes' => 'exclude-llmify',
        ],
    ];
    public bool $autoInjectDiscoveryTag = true;
    public bool $enableBotDetection = true;
    public array $additionalBotUserAgents = [];
    public bool $frontMatterInFullTxt = false;

    public function defineRules(): array
    {
        return [
            [
                [
                    'concurrentRequests',
                    'requestTimeout',
                ], 'required',
            ],
            [['concurrentRequests'], 'integer', 'min' => 1, 'max' => 100],
            [['requestTimeout'], 'integer', 'min' => 1],

        ];
    }
}
