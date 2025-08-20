<?php

namespace samuelreichor\llmify\models;

class LlmifyRefreshData
{
    /**
     * @var array{
     * sites: array{
     *      siteIds: array<int, bool>
     *  },
     *  elements: array<string, array{
     *      elementIds: array<int, bool>
     *  }>,
     *  urls: array<string, bool>
     * }
     */
    public array $data = [
        'sites' => [
            'siteIds' => [],
        ],
        'elements' => [],
        'urls' => [],
    ];

    public function addElementId(int $elId, string $elType): void
    {
        $this->data['elements'][$elType]['elementIds'][$elId] = true;
    }

    public function getElementIds(string $elementType): array
    {
        return $this->getKeysAsValues(['elements', $elementType, 'elementIds']);
    }

    public function addSiteId(int $siteId): void
    {
        $this->data['sites']['siteIds'][$siteId] = true;
    }

    public function getSiteIds(): array
    {
        return $this->getKeysAsValues(['sites', 'siteIds']);
    }

    public function addUrl(string $url): void
    {
        // query string here to bypass blitz caching
        $this->data['urls'][$url . '?llmify=true'] = true;
    }

    public function getUrls(): array
    {
        return $this->getKeysAsValues(['urls']);
    }

    private function getKeysAsValues(array $indexes): array
    {
        $keys = $this->data;

        foreach ($indexes as $index) {
            $keys = $keys[$index] ?? [];
        }

        return array_keys($keys);
    }

    public function isEmpty(): bool
    {
        return empty($this->data['elements']) && empty($this->data['sites']['siteIds']) && empty($this->data['urls']);
    }
}
