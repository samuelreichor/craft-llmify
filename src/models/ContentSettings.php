<?php

namespace samuelreichor\llmify\models;

use Craft;
use craft\base\Model;

/**
 * Content Settings model
 */
class ContentSettings extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;
    /**
     * @var string The field handle where the LLM title should come from
     */
    public string $llmTitleSource = 'title';
    /**
     * @var string Custom title if anyone wants to overwrite llmTitleSource
     */
    public string $llmTitle = '';
    /**
     * @var string The field handle where the LLM description should come from
     */
    public string $llmDescriptionSource = 'custom';
    /**
     * @var string Custom description if anyone wants to overwrite llmDescriptionSource
     */
    public string $llmDescription = '';
    /**
     * @var string A section title for all entries in the given section for llms.txt
     */
    public string $llmSectionTitle = '';
    /**
     * @var string A section description for all entries in the given section for llms.txt
     */
    public string $llmSectionDescription = '';
    /**
     * @var int Section ID of the content setting
     */
    public int $sectionId;
    /**
     * @var int Entry type ID of the content setting
     */
    public int $entryTypeId;
    /**
     * @var int Site ID of the content setting
     */
    public int $siteId;
    /**
     * @var bool If section is enabled
     */
    public bool $enabled = false;

    public function defineRules(): array
    {
        return [
            [
                [
                    'llmTitleSource',
                    'llmTitle',
                    'llmDescriptionSource',
                    'llmDescription',
                    'llmSectionTitle',
                    'llmSectionDescription',
                ], 'string'
            ],
        ];
    }
}
