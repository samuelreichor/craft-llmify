<?php

namespace samuelreichor\llmify\services;

use craft\base\Component;
use craft\elements\Entry;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\ContentSettings;

class MetadataService extends Component
{
    public ContentSettings $metaContent;
    public function __construct($entry)
    {
        parent::__construct();

        $sectionId = $entry->section->id;
        $this->metaContent = Llmify::getInstance()->settings->getContentSettingBySectionId($sectionId);
    }

    public function getLlmTitleByEntry(): string
    {
        // get the llm settings of the entry
        // return llm settings title ?? section title
        return $this->metaContent->llmTitle;
    }

    public function getLlmDescriptionByEntry(): string
    {
        // get the llm settings of the entry
        // return llm settings description ?? section description
        return $this->metaContent->llmDescription;
    }

    public function getMetaContentId(): string
    {
        return $this->metaContent->id;
    }
}
