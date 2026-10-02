<?php

namespace samuelreichor\llmify\models;

use craft\base\Model;

/**
 * Page model
 *
 * The page data the front matter of a markdown page is built from.
 */
class Page extends Model
{
    public string $title = '';
    public string $description = '';
    public array $elementMeta = ["uri" => "", "fullUrl" => ""];
}
