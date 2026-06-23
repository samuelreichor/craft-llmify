<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\errors\InvalidFieldException;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use samuelreichor\llmify\events\RegisterWebMcpToolsEvent;
use samuelreichor\llmify\Llmify;
use yii\base\Exception;

/**
 * WebMCP Service
 *
 * Backs the browser WebMCP (`document.modelContext`) integration: it builds the
 * client-side script that registers the tools and resolves the read-only data
 * those tools request. All content access reuses the same gating as the public
 * markdown routes — only LLMify-enabled, non-excluded content is ever exposed.
 *
 * Modules and plugins can extend the tool set via `EVENT_REGISTER_TOOLS`.
 *
 * @author Samuel Reichör <samuelreichor@gmail.com>
 */
class WebMcpService extends Component
{
    /**
     * Fired when the WebMCP tool set is built. Listeners can add custom tools
     * (with a client-side JS `handler`), modify the native definitions, or
     * remove tools entirely. See {@see RegisterWebMcpToolsEvent}.
     *
     * @event RegisterWebMcpToolsEvent
     */
    public const EVENT_REGISTER_TOOLS = 'registerTools';

    public const MAX_SEARCH_LIMIT = 25;
    public const DEFAULT_SEARCH_LIMIT = 10;
    public const MIN_QUERY_LENGTH = 2;
    public const MAX_QUERY_LENGTH = 200;

    /**
     * Builds the JavaScript that registers the WebMCP tools with the browser
     * agent. Tool descriptors come from `getTools()`; the `execute` handlers
     * for the native tools live in the script, custom tools bring their own
     * handler source. All data values are JSON-encoded to avoid script
     * injection; custom handler source is emitted as-is and is trusted the
     * same way any registered event handler is.
     */
    public function getScript(): string
    {
        $config = Json::encode([
            'searchUrl' => UrlHelper::actionUrl('llmify/web-mcp/search'),
            'pageUrl' => UrlHelper::actionUrl('llmify/web-mcp/page'),
            'sectionsUrl' => UrlHelper::actionUrl('llmify/web-mcp/sections'),
            'defaultLimit' => self::DEFAULT_SEARCH_LIMIT,
        ]);

        $definitions = [];
        $handlerEntries = [];

        foreach ($this->getTools() as $tool) {
            $definitions[] = [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'inputSchema' => $tool['inputSchema'] ?? ['type' => 'object'],
            ];

            if (isset($tool['handler'])) {
                $handlerEntries[] = Json::encode($tool['name']) . ': (' . $tool['handler'] . ')';
            }
        }

        $tools = Json::encode($definitions);
        $customHandlers = implode(",\n    ", $handlerEntries);

        return <<<JS
(() => {
  // Chrome 149 exposes the API on navigator.modelContext; Chrome 150+ moved it
  // to document.modelContext (navigator alias deprecated). Support both.
  const ctx = document.modelContext || navigator.modelContext;
  if (!ctx || typeof ctx.registerTool !== 'function') {
    return;
  }

  const CFG = {$config};
  const TOOLS = {$tools};

  const text = (value) => ({ content: [{ type: 'text', text: value }] });

  // Escape markdown link metacharacters so content-derived titles/descriptions
  // can't forge links or inject extra list entries in the result the agent reads.
  const mdEscape = (value) => String(value ?? '').replace(/[\\[\\]()\\\\]/g, '\\\\$&').replace(/[\\r\\n]+/g, ' ');

  // Never throw out of a tool: the agent handles a text result far better than
  // a failed invocation. Non-OK responses come back as { ok: false }.
  const fetchTool = async (url) => {
    try {
      const res = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        return { ok: false, status: res.status };
      }
      return { ok: true, data: await res.json() };
    } catch (e) {
      return { ok: false, status: 0 };
    }
  };

  // Passed to custom tool handlers so they get the same utilities as the
  // native tools.
  const helpers = { text, fetchTool, config: CFG };

  // Handlers contributed via the EVENT_REGISTER_TOOLS PHP event. A custom
  // handler with a native tool's name takes precedence, so tools can be
  // overridden.
  const customHandlers = {
    {$customHandlers}
  };

  const handlers = {
    search_content: async ({ query, limit }) => {
      const url = new URL(CFG.searchUrl, location.origin);
      url.searchParams.set('q', query);
      url.searchParams.set('limit', String(limit ?? CFG.defaultLimit));
      const r = await fetchTool(url);
      if (!r.ok) {
        return text('Search is currently unavailable on this site.');
      }
      if (!r.data.results || r.data.results.length === 0) {
        return text('No matching content found for "' + query + '".');
      }
      const lines = r.data.results.map(
        (item) => '- [' + mdEscape(item.title) + '](' + item.url + ')' + (item.description ? ': ' + mdEscape(item.description) : '')
      );
      return text(lines.join('\\n'));
    },
    get_page: async ({ uri }) => {
      const url = new URL(CFG.pageUrl, location.origin);
      url.searchParams.set('uri', uri);
      const r = await fetchTool(url);
      if (!r.ok) {
        return text('No page is available for URI "' + uri + '".');
      }
      return text(r.data.markdown || '');
    },
    list_sections: async () => {
      const r = await fetchTool(new URL(CFG.sectionsUrl, location.origin));
      if (!r.ok || !r.data.sections || r.data.sections.length === 0) {
        return text('No searchable sections are available.');
      }
      return text(r.data.sections.map((s) => '- ' + s.name).join('\\n'));
    },
    navigate_to: async ({ uri }) => {
      let target;
      try {
        target = new URL(uri, location.origin);
      } catch (e) {
        return text('Invalid URI: ' + uri);
      }
      if (target.origin !== location.origin) {
        return text('Refused to navigate to a different origin.');
      }
      // Defer the navigation so the tool result is delivered before the page
      // unloads; assigning synchronously tears down the call and the agent
      // sees a failed invocation instead of a result.
      setTimeout(() => location.assign(target.href), 50);
      return text('Navigating to ' + target.pathname);
    },
  };

  for (const tool of TOOLS) {
    const custom = customHandlers[tool.name];
    const execute = custom ? (input) => custom(input, helpers) : handlers[tool.name];
    if (!execute) {
      continue;
    }
    ctx.registerTool({
      name: tool.name,
      description: tool.description,
      inputSchema: tool.inputSchema,
      execute,
    });
  }
})();
JS;
    }

    /**
     * The final WebMCP tool set: the native definitions, extended/modified by
     * `EVENT_REGISTER_TOOLS` listeners. Tools without a name are dropped, and
     * when several tools share a name the last one wins, so appended tools can
     * override native ones.
     *
     * @return array<int, array{name: string, description?: string, inputSchema?: array<string, mixed>, handler?: string}>
     */
    public function getTools(): array
    {
        $tools = $this->getToolDefinitions();

        if ($this->hasEventHandlers(self::EVENT_REGISTER_TOOLS)) {
            $event = new RegisterWebMcpToolsEvent(['tools' => $tools]);
            $this->trigger(self::EVENT_REGISTER_TOOLS, $event);
            $tools = $event->tools;
        }

        $toolsByName = [];
        foreach ($tools as $tool) {
            $name = $tool['name'] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }
            $toolsByName[$name] = $tool;
        }

        return array_values($toolsByName);
    }

    /**
     * The native WebMCP tool descriptors (name, description, JSON Schema
     * input); only read-only tools are exposed, plus a client-side navigation
     * tool. Use `getTools()` for the final, event-extended tool set.
     *
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'name' => 'search_content',
                'description' => 'Search this website\'s content. Returns a list of matching pages with their title, description and URL.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search keywords.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return (1-' . self::MAX_SEARCH_LIMIT . ').',
                            'default' => self::DEFAULT_SEARCH_LIMIT,
                            'minimum' => 1,
                            'maximum' => self::MAX_SEARCH_LIMIT,
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'get_page',
                'description' => 'Fetch the full Markdown content of a single page on this website, identified by its URI (path).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'uri' => [
                            'type' => 'string',
                            'description' => 'The page URI/path, e.g. "news/my-article".',
                        ],
                    ],
                    'required' => ['uri'],
                ],
            ],
            [
                'name' => 'list_sections',
                'description' => 'List the content sections of this website that can be searched.',
                'inputSchema' => [
                    'type' => 'object',
                ],
            ],
            [
                'name' => 'navigate_to',
                'description' => 'Navigate the browser to a page on this website, identified by its URI (path).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'uri' => [
                            'type' => 'string',
                            'description' => 'The page URI/path to navigate to, e.g. "about".',
                        ],
                    ],
                    'required' => ['uri'],
                ],
            ],
        ];
    }

    /**
     * Full-text searches the LLMify-enabled content of a site and returns a
     * capped list of matches. Disabled sections, excluded elements and pages
     * without a URI are skipped, so the result is always a subset of the
     * already-public markdown pages.
     *
     * @return array<int, array{title: string, description: string, uri: string, url: string}>
     * @throws InvalidFieldException
     * @throws Exception
     */
    public function search(string $query, int $siteId, int $limit = self::DEFAULT_SEARCH_LIMIT): array
    {
        $limit = max(1, min($limit, self::MAX_SEARCH_LIMIT));

        $entrySectionIds = [];
        $productTypeIds = [];

        $commerceInstalled = HelperService::isCommerceInstalled();

        foreach ($this->_getServableContentSettings($siteId) as $contentSetting) {
            if ($contentSetting->elementType === Entry::class) {
                $entrySectionIds[] = $contentSetting->groupId;
            } elseif ($commerceInstalled && $contentSetting->elementType === \craft\commerce\elements\Product::class) {
                $productTypeIds[] = $contentSetting->groupId;
            }
        }

        // Fetch a headroom of candidates per type so that post-query filtering
        // (excluded elements, missing URIs) can't shrink the result below the
        // requested limit while servable matches still exist deeper down.
        $fetch = min($limit * 2, self::MAX_SEARCH_LIMIT);

        /** @var ElementInterface[] $elements */
        $elements = [];

        if (!empty($entrySectionIds)) {
            $elements = Entry::find()
                ->sectionId($entrySectionIds)
                ->siteId($siteId)
                ->search($query)
                ->orderBy('score')
                ->limit($fetch)
                ->all();
        }

        if (!empty($productTypeIds)) {
            $products = \craft\commerce\elements\Product::find()
                ->typeId($productTypeIds)
                ->siteId($siteId)
                ->search($query)
                ->orderBy('score')
                ->limit($fetch)
                ->all();
            $elements = array_merge($elements, $products);
        }

        // Entries and products come back as two separately ranked lists; sort
        // the merged set by search score so the best matches win across types.
        usort($elements, static fn(ElementInterface $a, ElementInterface $b) => ($b->searchScore ?? 0) <=> ($a->searchScore ?? 0));

        $results = [];

        foreach ($elements as $element) {
            if ($element->uri === null) {
                continue;
            }

            if (HelperService::isElementExcluded($element)) {
                continue;
            }

            $metadata = new MetadataService($element);
            $results[] = [
                'title' => $metadata->getLlmTitle(),
                'description' => $metadata->getLlmDescription(),
                'uri' => $element->uri,
                'url' => HelperService::getMarkdownUrl($element->uri, $siteId),
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Returns the LLMify-enabled, servable content sections for a site, used by
     * the `list_sections` tool.
     *
     * @return array<int, array{name: string, type: string}>
     */
    public function getSections(int $siteId): array
    {
        $sections = [];

        foreach ($this->_getServableContentSettings($siteId) as $contentSetting) {
            $name = $this->_resolveGroupName($contentSetting->groupId, $contentSetting->elementType);
            if ($name === null) {
                continue;
            }

            $sections[] = [
                'name' => $name,
                'type' => $contentSetting->elementType === Entry::class ? 'entries' : 'products',
            ];
        }

        return $sections;
    }

    /**
     * Returns the content settings for a site whose group is LLMify-enabled and
     * currently servable. Shared by `search()` and `getSections()` so both tools
     * gate on exactly the same content.
     *
     * @return array<int, \samuelreichor\llmify\models\ContentSettings>
     */
    private function _getServableContentSettings(int $siteId): array
    {
        $markdownService = Llmify::getInstance()->markdown;
        $contentSettings = Llmify::getInstance()->settings->getContentSettingsBySiteId($siteId);

        return array_values(array_filter(
            $contentSettings,
            static fn($contentSetting) => $markdownService->isGroupServable(
                $contentSetting->groupId,
                $siteId,
                $contentSetting->elementType,
            ),
        ));
    }

    /**
     * Resolves the display name of a section or product type.
     */
    private function _resolveGroupName(int $groupId, string $elementType): ?string
    {
        if ($elementType === Entry::class) {
            return Craft::$app->entries->getSectionById($groupId)?->name;
        }

        if (HelperService::isCommerceInstalled() && $elementType === \craft\commerce\elements\Product::class) {
            return \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeById($groupId)?->name;
        }

        return null;
    }
}
