<?php

namespace samuelreichor\llmify\models;

use craft\base\Model;

/**
 * Global Settings model
 */
class GlobalSettings extends Model
{
    public int $siteId;
    public bool $enabled = true;
    public bool $enableLlmsTxt = true;
    public bool $enableLlmsFullTxt = true;
    public string $llmTitle = '';
    public string $llmDescription = '';
    public string $llmNote = '';
    public bool $includeSocialLinks = false;
    public array $socialLinks = [];
    public array $frontMatterFields = [
        ['handle' => 'builtin:title', 'enabled' => false, 'label' => 'title'],
        ['handle' => 'builtin:description', 'enabled' => false, 'label' => 'description'],
        ['handle' => 'builtin:url', 'enabled' => false, 'label' => 'url'],
        ['handle' => 'builtin:date_modified', 'enabled' => false, 'label' => 'date_modified'],
    ];

    public function defineRules(): array
    {
        return [
            [
                [
                    'llmTitle',
                    'llmDescription',
                    'llmNote',
                ],
                'string',
            ],
        ];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
