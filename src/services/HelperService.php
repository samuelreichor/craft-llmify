<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\Section;

class HelperService extends Component
{
    private const textFieldOptionBase = [
        [
            'label' => 'Custom Text',
            'value' => 'custom',
        ],
        [
            'label' => '--- Fields ---',
            'disabled' => true,
        ],
        [
            'label' => 'Title',
            'value' => 'title',
        ],
    ];
    /**
     * @throws SiteNotFoundException
     */
    public function getCurrentCpSiteId(): ?int
    {
        $site = null;
        $siteHandle = Craft::$app->getRequest()->getQueryParam('site');

        $sites = Craft::$app->getSites();
        if ($siteHandle) {
            $site = $sites->getSiteByHandle($siteHandle);
        }

        if (!$site) {
            $site = $sites->getCurrentSite();
        }

        return $site->id;
    }

    public function getTextFieldsForSection(Section $section): array
    {
        $textFields = $this->getCommonTextFieldsForEntryTypes($section->getEntryTypes());
        return array_merge(
            self::textFieldOptionBase,
            $textFields
        );
    }

    public function getTextFieldsForEntry(Entry $entry): array
    {
        $textFields = $this->getCommonTextFieldsForEntryTypes([$entry->type]);

        return array_merge(
            self::textFieldOptionBase,
            $textFields
        );
    }

    /**
     * @param EntryType[] $entryTypes
     * @return array
     */
    private function getCommonTextFieldsForEntryTypes(array $entryTypes): array
    {
        if (empty($entryTypes)) {
            return [];
        }

        $allFieldHandles = [];
        foreach ($entryTypes as $entryType) {
            $fields = $entryType->getCustomFields();
            $textFields = array_filter($fields, function($field) {
                return $field instanceof PlainText;
            });
            $allFieldHandles[] = array_map(function($field) {
                return $field->handle;
            }, $textFields);
        }

        $commonFieldHandles = array_intersect(...$allFieldHandles);
        $commonTextFields = [];

        foreach ($commonFieldHandles as $handle) {
            $field = Craft::$app->fields->getFieldByHandle($handle);
            if ($field) {
                $commonTextFields[] = [
                    'label' => $field->name,
                    'value' => $field->handle,
                ];
            }
        }

        return $commonTextFields;
    }

    public function getFieldOfTypeFromEntry(Entry $entry, string $fieldClass): ?FieldInterface
    {
        $layout = $entry->getFieldLayout();

        if (!$layout) {
            return null;
        }

        $fields = $layout->getCustomFields();
        foreach ($fields as $field) {
            if ($field instanceof $fieldClass) {
                return $field;
            }
        }

        return null;
    }
}
