<?php

namespace samuelreichor\llmify\models;

use Craft;
use craft\base\Model;

/**
 * Global Settings model
 */
class GlobalSettings extends Model
{
    public int $siteId;
    public bool $enabled = true;
    public string $llmTitle = '';
    public string $llmDescription = '';
    public string $llmNote = '';

    public function defineRules(): array
    {
        return [
            [
                [
                    'llmTitle',
                    'llmDescription',
                    'llmNote'
                ],
                'string'
            ],
        ];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
