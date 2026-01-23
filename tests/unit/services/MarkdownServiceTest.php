<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\unit\services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use samuelreichor\llmify\services\MarkdownService;

/**
 * Unit tests for MarkdownService
 *
 * Note: Tests that require getCombinedHtml() are moved to integration tests
 * as they need the full Craft CMS environment due to Yii dependencies.
 */
#[CoversClass(MarkdownService::class)]
class MarkdownServiceTest extends TestCase
{
    private MarkdownService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MarkdownService();
    }

    protected function tearDown(): void
    {
        $this->service->clearBlocks();
        parent::tearDown();
    }

    #[Test]
    public function itHasEmptyContentBlocksByDefault(): void
    {
        $this->assertEmpty($this->service->contentBlocks);
        $this->assertEmpty($this->service->excludedContentBlocks);
        $this->assertNull($this->service->entryId);
        $this->assertNull($this->service->siteId);
    }

    #[Test]
    public function addContentBlockAddsHtmlToContentBlocks(): void
    {
        $html = '<p>Hello World</p>';
        $this->service->addContentBlock($html, 1, 1);

        $this->assertCount(1, $this->service->contentBlocks);
        $this->assertEquals($html, $this->service->contentBlocks[0]);
    }

    #[Test]
    public function addContentBlockSetsEntryAndSiteIdOnFirstCall(): void
    {
        $this->service->addContentBlock('<p>Test</p>', 42, 5);

        $this->assertEquals(42, $this->service->entryId);
        $this->assertEquals(5, $this->service->siteId);
    }

    #[Test]
    public function addContentBlockDoesNotOverwriteEntryAndSiteId(): void
    {
        $this->service->addContentBlock('<p>First</p>', 1, 1);
        $this->service->addContentBlock('<p>Second</p>', 2, 2);

        // Should still be the first values
        $this->assertEquals(1, $this->service->entryId);
        $this->assertEquals(1, $this->service->siteId);
    }

    #[Test]
    public function addExcludedContentBlockAddsHtmlToExcludedBlocks(): void
    {
        $html = '<div class="exclude">Hidden content</div>';
        $this->service->addExcludedContentBlock($html, 1, 1);

        $this->assertCount(1, $this->service->excludedContentBlocks);
        $this->assertEquals($html, $this->service->excludedContentBlocks[0]);
    }

    #[Test]
    public function addExcludedContentBlockSetsEntryAndSiteIdOnFirstCall(): void
    {
        $this->service->addExcludedContentBlock('<p>Excluded</p>', 42, 5);

        $this->assertEquals(42, $this->service->entryId);
        $this->assertEquals(5, $this->service->siteId);
    }

    #[Test]
    public function contentBlocksArrayContainsMultipleBlocks(): void
    {
        $this->service->addContentBlock('<p>First paragraph</p>', 1, 1);
        $this->service->addContentBlock('<p>Second paragraph</p>', 1, 1);
        $this->service->addContentBlock('<p>Third paragraph</p>', 1, 1);

        $this->assertCount(3, $this->service->contentBlocks);
        $this->assertStringContainsString('First paragraph', $this->service->contentBlocks[0]);
        $this->assertStringContainsString('Second paragraph', $this->service->contentBlocks[1]);
        $this->assertStringContainsString('Third paragraph', $this->service->contentBlocks[2]);
    }

    #[Test]
    public function excludedBlocksAreStoredSeparately(): void
    {
        $includedHtml = '<p>Visible content</p>';
        $excludedHtml = '<div class="secret">Hidden content</div>';

        $this->service->addContentBlock($includedHtml, 1, 1);
        $this->service->addExcludedContentBlock($excludedHtml, 1, 1);

        $this->assertCount(1, $this->service->contentBlocks);
        $this->assertCount(1, $this->service->excludedContentBlocks);
        $this->assertStringContainsString('Visible content', $this->service->contentBlocks[0]);
        $this->assertStringContainsString('Hidden content', $this->service->excludedContentBlocks[0]);
    }

    #[Test]
    public function clearBlocksResetsAllState(): void
    {
        $this->service->addContentBlock('<p>Content</p>', 1, 1);
        $this->service->addExcludedContentBlock('<p>Excluded</p>', 1, 1);

        $this->service->clearBlocks();

        $this->assertEmpty($this->service->contentBlocks);
        $this->assertEmpty($this->service->excludedContentBlocks);
        $this->assertNull($this->service->entryId);
        $this->assertNull($this->service->siteId);
    }

    #[Test]
    public function multipleContentBlocksAreStoredInOrder(): void
    {
        $this->service->addContentBlock('<h1>Title</h1>', 1, 1);
        $this->service->addContentBlock('<p>Intro paragraph</p>', 1, 1);
        $this->service->addContentBlock('<h2>Section</h2>', 1, 1);
        $this->service->addContentBlock('<p>Section content</p>', 1, 1);

        $this->assertCount(4, $this->service->contentBlocks);
        $this->assertEquals('<h1>Title</h1>', $this->service->contentBlocks[0]);
        $this->assertEquals('<p>Intro paragraph</p>', $this->service->contentBlocks[1]);
        $this->assertEquals('<h2>Section</h2>', $this->service->contentBlocks[2]);
        $this->assertEquals('<p>Section content</p>', $this->service->contentBlocks[3]);
    }

    #[Test]
    public function multipleExcludedBlocksAreStoredInOrder(): void
    {
        $this->service->addExcludedContentBlock('<div class="ad">Ad 1</div>', 1, 1);
        $this->service->addExcludedContentBlock('<div class="promo">Promo</div>', 1, 1);

        $this->assertCount(2, $this->service->excludedContentBlocks);
        $this->assertEquals('<div class="ad">Ad 1</div>', $this->service->excludedContentBlocks[0]);
        $this->assertEquals('<div class="promo">Promo</div>', $this->service->excludedContentBlocks[1]);
    }

    #[Test]
    #[DataProvider('provideHtmlContentBlocks')]
    public function itStoresVariousHtmlStructures(string $html, string $expectedContent): void
    {
        $this->service->addContentBlock($html, 1, 1);

        $this->assertCount(1, $this->service->contentBlocks);
        $this->assertStringContainsString($expectedContent, $this->service->contentBlocks[0]);
    }

    public static function provideHtmlContentBlocks(): array
    {
        return [
            'simple paragraph' => ['<p>Simple text</p>', 'Simple text'],
            'heading' => ['<h1>Main Title</h1>', 'Main Title'],
            'nested elements' => ['<div><p><strong>Bold text</strong></p></div>', 'Bold text'],
            'list' => ['<ul><li>Item 1</li><li>Item 2</li></ul>', 'Item 1'],
            'link' => ['<a href="https://example.com">Link text</a>', 'Link text'],
            'multiple paragraphs' => ['<p>First</p><p>Second</p>', 'First'],
        ];
    }

    #[Test]
    public function itCanHandleHtmlWithSpecialCharacters(): void
    {
        $html = '<p>Special chars: &amp; &lt; &gt; "quotes" \'apostrophes\'</p>';
        $this->service->addContentBlock($html, 1, 1);

        $this->assertCount(1, $this->service->contentBlocks);
        $this->assertStringContainsString('Special chars', $this->service->contentBlocks[0]);
    }

    #[Test]
    public function itCanHandleUnicodeContent(): void
    {
        $html = '<p>Unicode: 日本語 中文 한국어 émojis 🚀🎉</p>';
        $this->service->addContentBlock($html, 1, 1);

        $this->assertStringContainsString('日本語', $this->service->contentBlocks[0]);
        $this->assertStringContainsString('🚀', $this->service->contentBlocks[0]);
    }

    #[Test]
    public function itCanHandleComplexNestedHtml(): void
    {
        $html = '<article><header><h1>Title</h1></header><main><p>Content</p></main></article>';
        $this->service->addContentBlock($html, 1, 1);

        $this->assertStringContainsString('Title', $this->service->contentBlocks[0]);
        $this->assertStringContainsString('Content', $this->service->contentBlocks[0]);
    }

    #[Test]
    public function entryIdCanBeSetFromExcludedBlockIfNotAlreadySet(): void
    {
        // First add excluded block
        $this->service->addExcludedContentBlock('<p>Excluded</p>', 100, 200);

        $this->assertEquals(100, $this->service->entryId);
        $this->assertEquals(200, $this->service->siteId);

        // Then add content block - should not overwrite
        $this->service->addContentBlock('<p>Content</p>', 300, 400);

        $this->assertEquals(100, $this->service->entryId);
        $this->assertEquals(200, $this->service->siteId);
    }

    #[Test]
    public function clearBlocksCanBeCalledMultipleTimes(): void
    {
        $this->service->addContentBlock('<p>Content 1</p>', 1, 1);
        $this->service->clearBlocks();

        $this->service->addContentBlock('<p>Content 2</p>', 2, 2);
        $this->service->clearBlocks();

        $this->assertEmpty($this->service->contentBlocks);
        $this->assertNull($this->service->entryId);
    }

    #[Test]
    public function itCanHandleEmptyHtml(): void
    {
        $this->service->addContentBlock('', 1, 1);

        $this->assertCount(1, $this->service->contentBlocks);
        $this->assertEquals('', $this->service->contentBlocks[0]);
    }

    #[Test]
    public function itCanHandleWhitespaceOnlyHtml(): void
    {
        $this->service->addContentBlock('   ', 1, 1);

        $this->assertCount(1, $this->service->contentBlocks);
        $this->assertEquals('   ', $this->service->contentBlocks[0]);
    }

    #[Test]
    public function blocksCanBeMixedFreely(): void
    {
        $this->service->addContentBlock('<p>Content 1</p>', 1, 1);
        $this->service->addExcludedContentBlock('<p>Excluded 1</p>', 1, 1);
        $this->service->addContentBlock('<p>Content 2</p>', 1, 1);
        $this->service->addExcludedContentBlock('<p>Excluded 2</p>', 1, 1);
        $this->service->addContentBlock('<p>Content 3</p>', 1, 1);

        $this->assertCount(3, $this->service->contentBlocks);
        $this->assertCount(2, $this->service->excludedContentBlocks);
    }
}
