<?php

namespace samuelreichor\llmify\models;

use craft\base\Model;

/**
 * Page model
 */
class Page extends Model
{
    public ?int $id = null;
    public ?int $siteId = null;
    public ?int $groupId = null;
    public ?int $elementId = null;
    public ?string $elementType = null;
    public ?int $metadataId = null;
    public string $content = '';
    public string $title = '';
    public string $description = '';
    public array $elementMeta = ["uri" => "", "fullUrl" => ""];
}
