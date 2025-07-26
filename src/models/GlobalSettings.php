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

    public function defineRules(): array
    {
        return [
            [
                [
                    'llmTitle',
                    'llmDescription',
                ],
                'string'
            ],
        ];
    }
}
