<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\elements\Entry;
use samuelreichor\llmify\Llmify;

class MetadataService extends Component
{
    public function getEntryTitle(Entry $entry): string
    {
        // This will be implemented in a future step.
        return '';
    }

    public function getEntryDescription(Entry $entry): string
    {
        // This will be implemented in a future step.
        return '';
    }
}
