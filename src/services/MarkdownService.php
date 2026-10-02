<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\web\View;
use Exception;
use League\HTMLToMarkdown\HtmlConverter;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\models\ContentSettings;
use samuelreichor\llmify\models\Page;

class MarkdownService extends Component
{
    public array $contentBlocks = [];
    public array $excludedContentBlocks = [];
    public ?int $entryId = null;
    public ?int $siteId = null;

    /**
     * True while the route of an element is resolved for rendering its
     * markdown, so auto-serve does not reroute that lookup to itself.
     */
    public bool $isResolvingRoute = false;

    /**
     * Converts a full HTML document (e.g. a fetched front-end page in headless mode)
     * into markdown. Scopes to the `<body>` to drop `<head>` noise, removes excluded
     * CSS classes, then runs the standard HTML-to-markdown conversion.
     *
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     * @throws CircularException
     * @throws StrictException
     */
    public function convertHtml(string $html): string
    {
        $bodyHtml = $this->extractBody($html);
        $cleaned = (string)$this->removeTags($bodyHtml);

        return $this->htmlToMarkdown($cleaned);
    }

    public function addContentBlock(string $html, int $entryId, int $siteId): void
    {
        $this->contentBlocks[] = $html;
        if ($this->entryId === null) {
            $this->entryId = $entryId;
            $this->siteId = $siteId;
        }
    }

    public function addExcludedContentBlock(string $html, int $entryId, int $siteId): void
    {
        $this->excludedContentBlocks[] = $html;
        if ($this->entryId === null) {
            $this->entryId = $entryId;
            $this->siteId = $siteId;
        }
    }

    /**
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     * @throws CircularException
     * @throws StrictException
     */
    public function getCombinedHtml(): string
    {
        $fullHtml = implode('', $this->contentBlocks);

        // removes content in excludeLlmify tags
        if (!empty($this->excludedContentBlocks)) {
            $fullHtml = str_replace($this->excludedContentBlocks, '', $fullHtml);
        }

        return $this->removeTags($fullHtml);
    }

    public function clearBlocks(): void
    {
        $this->contentBlocks = [];
        $this->excludedContentBlocks = [];
        $this->entryId = null;
        $this->siteId = null;
    }

    public function findElementByUri(string $uri, int $siteId): ?ElementInterface
    {
        $element = Entry::find()->uri($uri)->siteId($siteId)->one();

        if (!$element && HelperService::isCommerceInstalled()) {
            $element = \craft\commerce\elements\Product::find()->uri($uri)->siteId($siteId)->one();
        }

        return $element;
    }

    /**
     * @throws Exception
     */
    public function getMarkdownByUri(string $uri, int $siteId): string
    {
        $element = $this->findElementByUri($uri, $siteId);

        return $element ? $this->getPageMarkdown($element) : '';
    }

    /**
     * @throws Exception
     */
    public function getPageMarkdown(ElementInterface $element): string
    {
        if (!$this->isServable($element)) {
            return '';
        }

        return HelperService::cached(
            $this->getPageCacheKey($element),
            fn() => $this->buildPageMarkdown($element),
        );
    }

    public function getPageCacheKey(ElementInterface $element): array
    {
        return [Constants::CACHE_TAG, 'page', $element->id, $element->siteId];
    }

    /**
     * @return ElementInterface[]
     * @throws Exception
     */
    public function getServableElements(int $siteId): array
    {
        $elements = [];

        foreach (Llmify::getInstance()->settings->getContentSettingsBySiteId($siteId) as $contentSetting) {
            if (!$this->isGroupServable($contentSetting->groupId, $siteId, $contentSetting->elementType)) {
                continue;
            }

            foreach ($this->findElementsForContentSetting($contentSetting, $siteId) as $element) {
                if ($this->isServable($element)) {
                    $elements[] = $element;
                }
            }
        }

        return $elements;
    }

    /**
     * @return ElementInterface[]
     */
    public function findElementsForContentSetting(ContentSettings $contentSetting, int $siteId): array
    {
        if ($contentSetting->elementType === Entry::class) {
            return Entry::find()
                ->sectionId($contentSetting->groupId)
                ->siteId($siteId)
                ->all();
        }

        if (HelperService::isCommerceInstalled() && $contentSetting->elementType === \craft\commerce\elements\Product::class) {
            return \craft\commerce\elements\Product::find()
                ->typeId($contentSetting->groupId)
                ->siteId($siteId)
                ->all();
        }

        return [];
    }

    /**
     * Site templates can only be rendered reliably in a web request, so the
     * `.md` URLs are requested instead of rendering in-process.
     *
     * @param ElementInterface[] $elements Pages of a single site.
     * @param callable|null $onProgress Called with the number of handled pages and the total.
     * @return int The number of pages that are cached afterwards.
     * @throws Exception
     */
    public function generate(array $elements, ?callable $onProgress = null): int
    {
        $total = count($elements);
        $generated = 0;
        $handled = 0;

        if (Llmify::getInstance()->getSettings()->headlessMode) {
            foreach ($elements as $element) {
                if ($this->getPageMarkdown($element) !== '') {
                    $generated++;
                }
                $onProgress && $onProgress(++$handled, $total);
            }

            return $generated;
        }

        $request = Llmify::getInstance()->request;

        foreach (array_chunk($elements, 10) as $chunk) {
            $urls = array_map(fn($element) => HelperService::getMarkdownUrl($element->uri, $element->siteId), $chunk);
            $bodies = $request->fetchAll($urls, $chunk[0]->siteId);
            $generated += count(array_filter($bodies, fn($body) => $body !== null));
            $handled += count($chunk);
            $onProgress && $onProgress($handled, $total);
        }

        return $generated;
    }

    /**
     * @return array{total: int, cached: int, avgTokens: int, oldestCached: int|null}
     * @throws Exception
     */
    public function getCacheStats(int $siteId): array
    {
        $elements = $this->getServableElements($siteId);
        $cached = 0;
        $tokens = 0;
        $oldestCached = null;

        foreach ($elements as $element) {
            $entry = HelperService::getCached($this->getPageCacheKey($element));

            if ($entry === null) {
                continue;
            }

            $cached++;
            $oldestCached = min($oldestCached ?? $entry['dateCached'], $entry['dateCached']);
            $tokens += self::estimateTokens($entry['value']);
        }

        return [
            'total' => count($elements),
            'cached' => $cached,
            'avgTokens' => $cached > 0 ? (int)round($tokens / $cached) : 0,
            'oldestCached' => $oldestCached,
        ];
    }

    public static function estimateTokens(string $markdown): int
    {
        return (int)ceil(mb_strlen($markdown) / 4);
    }

    /**
     * @throws Exception
     */
    public function isServable(ElementInterface $element): bool
    {
        if (!HelperService::isEntryOrProduct($element)) {
            return false;
        }

        if ($element->uri === null) {
            return false;
        }

        $groupId = HelperService::getGroupIdForElement($element);

        if ($groupId === null || !$this->isGroupServable($groupId, $element->siteId, $element::class)) {
            return false;
        }

        return !HelperService::isElementExcluded($element);
    }

    public function isGroupServable(int $groupId, int $siteId, ?string $elementType = null): bool
    {
        $elementType = $elementType ?? Entry::class;
        $settings = Llmify::getInstance()->settings;

        return $settings->getGlobalSetting($siteId)->isEnabled()
            && $settings->getContentSetting($groupId, $siteId, $elementType)->isEnabled();
    }

    /**
     * @throws Exception
     */
    private function buildPageMarkdown(ElementInterface $element): string
    {
        // The element itself was loaded before the cache info got collected.
        Craft::$app->getElements()->collectCacheInfoForElement($element);

        $markdown = Llmify::getInstance()->getSettings()->headlessMode
            ? $this->fetchMarkdown($element)
            : $this->renderMarkdown($element);

        if ($markdown === null) {
            return '';
        }

        $metadata = new MetadataService($element);
        $page = new Page([
            'title' => $metadata->getLlmTitle(),
            'description' => $metadata->getLlmDescription(),
            'elementMeta' => [
                'fullUrl' => $element->getUrl(),
                'uri' => $element->uri,
            ],
        ]);

        return Llmify::getInstance()->frontMatter->prependFrontMatter($markdown, $page, $element);
    }

    public function getOriginalRoute(ElementInterface $element): mixed
    {
        $this->isResolvingRoute = true;

        try {
            return $element->getRoute();
        } finally {
            $this->isResolvingRoute = false;
        }
    }

    /**
     * @throws Exception
     */
    private function renderMarkdown(ElementInterface $element): ?string
    {
        $route = $this->getOriginalRoute($element);
        $template = is_array($route) && ($route[0] ?? null) === 'templates/render'
            ? ($route[1]['template'] ?? null)
            : null;

        if (!is_string($template) || $template === '') {
            Craft::warning("No template to render markdown for element {$element->id} in site {$element->siteId}.", 'llmify');
            return null;
        }

        Craft::$app->getUrlManager()->setMatchedElement($element);
        $this->clearBlocks();

        try {
            Craft::$app->getView()->renderPageTemplate($template, $route[1]['variables'] ?? [], View::TEMPLATE_MODE_SITE);
            $html = $this->getCombinedHtml();
        } finally {
            $this->clearBlocks();
        }

        return $html === '' ? null : $this->htmlToMarkdown($html);
    }

    /**
     * @throws Exception
     */
    private function fetchMarkdown(ElementInterface $element): ?string
    {
        $url = $element->getUrl();

        if (!$url) {
            return null;
        }

        try {
            return Llmify::getInstance()->request->fetchAndConvert($url);
        } catch (\Throwable $e) {
            Craft::warning("Markdown generation failed for {$url}. " . $e->getMessage(), 'llmify');
            return null;
        }
    }

    private function htmlToMarkdown(string $html): string
    {
        $config = Llmify::getInstance()->getSettings()->markdownConfig;
        $converter = new HtmlConverter($config);

        $markdownRaw = $converter->convert($html);

        // Clean up markdown links: strip leftover HTML tags and normalize whitespace
        $markdownRaw = preg_replace_callback('/\[((?:[^\[\]]|\[[^\]]*\])*)\]\(([^)]+)\)/', function($match) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($match[1])));
            if ($text === '') {
                return '';
            }
            return "[{$text}]({$match[2]})";
        }, $markdownRaw);

        // Remove empty list items left behind after node removal
        $markdownRaw = preg_replace('/^[ \t]*-[ \t]*$/m', '', $markdownRaw);

        // Decode HTML entities (e.g. &amp; → &, &nbsp; → space)
        $markdownRaw = html_entity_decode($markdownRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/(\n[ \t]*){2,}/', "\n\n", $markdownRaw);
    }

    /**
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     * @throws CircularException
     * @throws StrictException
     */
    private function removeTags(string $html): string
    {
        // Fix malformed HTML where attributes are glued together without spaces
        // (e.g. class="foo"src="bar") — PHPHtmlParser breaks on these, outputting
        // partial attribute values as text nodes
        $html = preg_replace('/"([a-zA-Z-]+=)/', '" $1', $html);

        $dom = new Dom();
        $dom->loadStr($html);
        $excludedClass = $this->getExcludeClass();

        if ($excludedClass !== '') {
            foreach ($dom->find($excludedClass) as $node) {
                $node->delete();
            }
        }

        return $dom;
    }

    /**
     * Returns the inner HTML of the document's `<body>`, falling back to the
     * original string if no body can be parsed (e.g. an HTML fragment).
     */
    private function extractBody(string $html): string
    {
        try {
            $dom = new Dom();
            $dom->loadStr($html);
            $body = $dom->find('body', 0);

            if ($body !== null) {
                return $body->innerHtml();
            }
        } catch (Exception) {
            // Fall through and convert the document as-is.
        }

        return $html;
    }

    private function getExcludeClass(): string
    {
        $excludedClasses = Llmify::getInstance()->getSettings()->excludeClasses;
        return implode(',', array_map(function($n) {
            return ".{$n['classes']}";
        }, $excludedClasses));
    }
}
