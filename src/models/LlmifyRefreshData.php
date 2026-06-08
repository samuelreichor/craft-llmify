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
     *  urls: array<string, array{elementId: int|null, siteId: int|null}>
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

    public function addUrl(string $url, ?int $elementId = null, ?int $siteId = null): void
    {
        // query string here to bypass blitz caching
        $this->data['urls'][$url . '?llmify=true'] = [
            'elementId' => $elementId,
            'siteId' => $siteId,
        ];
    }

    public function getUrls(): array
    {
        return $this->getKeysAsValues(['urls']);
    }

    /**
     * Returns the queued URLs as a list of items carrying their element context.
     * Headless generation needs the element/site to associate the fetched body
     * with the right page (Twig mode resolves this server-side during render).
     *
     * @return array<int, array{url: string, elementId: int|null, siteId: int|null}>
     */
    public function getUrlItems(): array
    {
        $items = [];

        foreach ($this->data['urls'] as $url => $meta) {
            $items[] = [
                'url' => $url,
                'elementId' => $meta['elementId'] ?? null,
                'siteId' => $meta['siteId'] ?? null,
            ];
        }

        return $items;
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
