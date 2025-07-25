<?php

namespace samuelreichor\llmify\models;

use Craft;
use craft\base\Model;

/**
 * Page model
 */
class Page extends Model
{
    public ?int $id = null;
    public ?int $siteId = null;
    public ?int $sectionId = null;
    public ?int $entryId = null;
    public ?int $metadataId = null;
    public string $content = '';
    public string $title = '';
    public string $description = '';
}
