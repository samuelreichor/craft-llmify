<?php

namespace samuelreichor\llmify\models;

use craft\base\Model;

class PluginSettings extends Model
{
    public bool $isEnabled = true;
    public bool $isRealUrlLlm = false;
    public string $markdownUrlPrefix = 'raw';
    public int $concurrentRequests = 3;
    public int $requestTimeout = 100;
    public array $markdownConfig = [
        'strip_tags' => true,
        'header_style' => 'atx',
        'remove_nodes' => 'img picture style',
    ];

    public function defineRules(): array
    {
        return [
            [
                [
                    'markdownUrlPrefix',
                    'concurrentRequests',
                    'requestTimeout',
                ], 'required',
            ],
            [['concurrentRequests'], 'integer', 'min' => 1, 'max' => 100],
            [['requestTimeout'], 'integer', 'min' => 1],

        ];
    }
}
