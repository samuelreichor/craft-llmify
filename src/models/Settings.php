<?php

namespace samuelreichor\llmify\models;

use Craft;
use craft\base\Model;

/**
 * llmify settings
 */
class Settings extends Model
{
    public int $cacheTtl = 3600;
}
