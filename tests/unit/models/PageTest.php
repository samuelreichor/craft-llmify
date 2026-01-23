<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\unit\models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use samuelreichor\llmify\models\Page;

#[CoversClass(Page::class)]
class PageTest extends TestCase
{
    #[Test]
    public function itHasCorrectDefaultValues(): void
    {
        $page = new Page();

        $this->assertNull($page->id);
        $this->assertNull($page->siteId);
        $this->assertNull($page->sectionId);
        $this->assertNull($page->entryId);
        $this->assertNull($page->metadataId);
        $this->assertEquals('', $page->content);
        $this->assertEquals('', $page->title);
        $this->assertEquals('', $page->description);
    }

    #[Test]
    public function itHasCorrectDefaultEntryMeta(): void
    {
        $page = new Page();

        $expectedMeta = [
            'uri' => '',
            'fullUrl' => '',
        ];

        $this->assertEquals($expectedMeta, $page->entryMeta);
    }

    #[Test]
    public function itCanBeInitializedWithConfig(): void
    {
        $config = [
            'id' => 1,
            'siteId' => 2,
            'sectionId' => 3,
            'entryId' => 4,
            'metadataId' => 5,
            'content' => '# Hello World',
            'title' => 'Test Page',
            'description' => 'A test description',
            'entryMeta' => [
                'uri' => 'test/page',
                'fullUrl' => 'https://example.com/test/page',
            ],
        ];

        $page = new Page($config);

        $this->assertEquals(1, $page->id);
        $this->assertEquals(2, $page->siteId);
        $this->assertEquals(3, $page->sectionId);
        $this->assertEquals(4, $page->entryId);
        $this->assertEquals(5, $page->metadataId);
        $this->assertEquals('# Hello World', $page->content);
        $this->assertEquals('Test Page', $page->title);
        $this->assertEquals('A test description', $page->description);
        $this->assertEquals('test/page', $page->entryMeta['uri']);
        $this->assertEquals('https://example.com/test/page', $page->entryMeta['fullUrl']);
    }

    #[Test]
    public function itCanSetPropertiesAfterInitialization(): void
    {
        $page = new Page();

        $page->id = 10;
        $page->siteId = 20;
        $page->sectionId = 30;
        $page->entryId = 40;
        $page->metadataId = 50;
        $page->content = '## Content';
        $page->title = 'Updated Title';
        $page->description = 'Updated Description';
        $page->entryMeta = [
            'uri' => 'updated/uri',
            'fullUrl' => 'https://example.com/updated/uri',
        ];

        $this->assertEquals(10, $page->id);
        $this->assertEquals(20, $page->siteId);
        $this->assertEquals(30, $page->sectionId);
        $this->assertEquals(40, $page->entryId);
        $this->assertEquals(50, $page->metadataId);
        $this->assertEquals('## Content', $page->content);
        $this->assertEquals('Updated Title', $page->title);
        $this->assertEquals('Updated Description', $page->description);
        $this->assertEquals('updated/uri', $page->entryMeta['uri']);
    }

    #[Test]
    public function itCanHandleMultilineMarkdownContent(): void
    {
        $markdownContent = <<<MARKDOWN
# Main Title

## Section 1

This is a paragraph with some **bold** and *italic* text.

- List item 1
- List item 2
- List item 3

## Section 2

Another paragraph here.
MARKDOWN;

        $page = new Page();
        $page->content = $markdownContent;

        $this->assertStringContainsString('# Main Title', $page->content);
        $this->assertStringContainsString('## Section 1', $page->content);
        $this->assertStringContainsString('**bold**', $page->content);
        $this->assertStringContainsString('- List item 1', $page->content);
    }

    #[Test]
    public function itHandlesJsonEntryMeta(): void
    {
        // Simulate what might come from a database JSON field
        $jsonString = '{"uri":"blog/post-1","fullUrl":"https://example.com/blog/post-1"}';
        $entryMeta = json_decode($jsonString, true);

        $page = new Page([
            'entryMeta' => $entryMeta,
        ]);

        $this->assertEquals('blog/post-1', $page->entryMeta['uri']);
        $this->assertEquals('https://example.com/blog/post-1', $page->entryMeta['fullUrl']);
    }

    #[Test]
    public function itHandlesEmptyContent(): void
    {
        $page = new Page([
            'content' => '',
            'title' => 'Empty Page',
            'description' => '',
        ]);

        $this->assertEquals('', $page->content);
        $this->assertEquals('Empty Page', $page->title);
        $this->assertEquals('', $page->description);
    }

    #[Test]
    public function itCanHavePartialConfiguration(): void
    {
        $page = new Page([
            'siteId' => 1,
            'title' => 'Partial Config',
        ]);

        // Set values should be present
        $this->assertEquals(1, $page->siteId);
        $this->assertEquals('Partial Config', $page->title);

        // Unset values should have defaults
        $this->assertNull($page->id);
        $this->assertNull($page->sectionId);
        $this->assertEquals('', $page->content);
    }
}
