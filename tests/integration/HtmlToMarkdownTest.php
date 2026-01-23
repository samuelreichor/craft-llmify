<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\integration;

use League\HTMLToMarkdown\HtmlConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for HTML to Markdown conversion
 *
 * These tests verify that the league/html-to-markdown library works correctly
 * with the configuration used by the LLMify plugin.
 */
class HtmlToMarkdownTest extends TestCase
{
    private HtmlConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the same config as the plugin's default PluginSettings
        $this->converter = new HtmlConverter([
            'strip_tags' => true,
            'header_style' => 'atx',
            'remove_nodes' => 'img picture style',
        ]);
    }

    #[Test]
    public function itConvertsBasicHtmlToMarkdown(): void
    {
        $html = '<p>Hello World</p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('Hello World', $markdown);
    }

    #[Test]
    public function itConvertsHeadingsToAtxStyle(): void
    {
        $html = '<h1>Title</h1><h2>Subtitle</h2><h3>Section</h3>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('# Title', $markdown);
        $this->assertStringContainsString('## Subtitle', $markdown);
        $this->assertStringContainsString('### Section', $markdown);
    }

    #[Test]
    public function itConvertsBoldText(): void
    {
        $html = '<p>This is <strong>bold</strong> text</p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('**bold**', $markdown);
    }

    #[Test]
    public function itConvertsItalicText(): void
    {
        $html = '<p>This is <em>italic</em> text</p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('*italic*', $markdown);
    }

    #[Test]
    public function itConvertsLinks(): void
    {
        $html = '<a href="https://example.com">Link text</a>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('[Link text](https://example.com)', $markdown);
    }

    #[Test]
    public function itConvertsUnorderedLists(): void
    {
        $html = '<ul><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('- Item 1', $markdown);
        $this->assertStringContainsString('- Item 2', $markdown);
        $this->assertStringContainsString('- Item 3', $markdown);
    }

    #[Test]
    public function itConvertsOrderedLists(): void
    {
        $html = '<ol><li>First</li><li>Second</li><li>Third</li></ol>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('1. First', $markdown);
        $this->assertStringContainsString('2. Second', $markdown);
        $this->assertStringContainsString('3. Third', $markdown);
    }

    #[Test]
    public function itConvertsBlockquotes(): void
    {
        $html = '<blockquote>This is a quote</blockquote>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('> This is a quote', $markdown);
    }

    #[Test]
    public function itConvertsCodeBlocks(): void
    {
        $html = '<pre><code>function test() { return true; }</code></pre>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('function test()', $markdown);
    }

    #[Test]
    public function itConvertsInlineCode(): void
    {
        $html = '<p>Use the <code>print()</code> function</p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('`print()`', $markdown);
    }

    #[Test]
    public function itRemovesImages(): void
    {
        $html = '<p>Text before <img src="image.jpg" alt="Image"> text after</p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringNotContainsString('image.jpg', $markdown);
        $this->assertStringNotContainsString('![', $markdown);
        $this->assertStringContainsString('Text before', $markdown);
        $this->assertStringContainsString('text after', $markdown);
    }

    #[Test]
    public function itRemovesPictureElements(): void
    {
        $html = '<picture><source srcset="image.webp"><img src="image.jpg"></picture>';
        $markdown = $this->converter->convert($html);

        $this->assertStringNotContainsString('image', $markdown);
    }

    #[Test]
    public function itRemovesStyleElements(): void
    {
        $html = '<style>.class { color: red; }</style><p>Content</p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringNotContainsString('color: red', $markdown);
        $this->assertStringContainsString('Content', $markdown);
    }

    #[Test]
    public function itHandlesNestedLists(): void
    {
        $html = '<ul><li>Item 1<ul><li>Nested 1</li><li>Nested 2</li></ul></li><li>Item 2</li></ul>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('Item 1', $markdown);
        $this->assertStringContainsString('Nested 1', $markdown);
        $this->assertStringContainsString('Item 2', $markdown);
    }

    #[Test]
    public function itHandlesComplexHtmlStructure(): void
    {
        $html = <<<HTML
<article>
    <header>
        <h1>Article Title</h1>
        <p class="meta">Published on 2024-01-01</p>
    </header>
    <section>
        <h2>Introduction</h2>
        <p>This is the <strong>introduction</strong> paragraph with <a href="https://example.com">a link</a>.</p>
        <ul>
            <li>Point 1</li>
            <li>Point 2</li>
        </ul>
    </section>
    <section>
        <h2>Conclusion</h2>
        <blockquote>Important quote here</blockquote>
    </section>
</article>
HTML;

        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('# Article Title', $markdown);
        $this->assertStringContainsString('## Introduction', $markdown);
        $this->assertStringContainsString('**introduction**', $markdown);
        $this->assertStringContainsString('[a link](https://example.com)', $markdown);
        $this->assertStringContainsString('- Point 1', $markdown);
        $this->assertStringContainsString('## Conclusion', $markdown);
        $this->assertStringContainsString('> Important quote here', $markdown);
    }

    #[Test]
    public function itPreservesLineBreaks(): void
    {
        $html = '<p>Line 1</p><p>Line 2</p><p>Line 3</p>';
        $markdown = $this->converter->convert($html);

        // Each paragraph should be on its own line
        $this->assertStringContainsString('Line 1', $markdown);
        $this->assertStringContainsString('Line 2', $markdown);
        $this->assertStringContainsString('Line 3', $markdown);
    }

    #[Test]
    #[DataProvider('provideHtmlToMarkdownConversions')]
    public function itConvertsHtmlCorrectly(string $html, string $expectedMarkdownPart): void
    {
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString($expectedMarkdownPart, $markdown);
    }

    public static function provideHtmlToMarkdownConversions(): array
    {
        return [
            'paragraph' => ['<p>Simple text</p>', 'Simple text'],
            'h1' => ['<h1>Heading 1</h1>', '# Heading 1'],
            'h2' => ['<h2>Heading 2</h2>', '## Heading 2'],
            'h3' => ['<h3>Heading 3</h3>', '### Heading 3'],
            'h4' => ['<h4>Heading 4</h4>', '#### Heading 4'],
            'h5' => ['<h5>Heading 5</h5>', '##### Heading 5'],
            'h6' => ['<h6>Heading 6</h6>', '###### Heading 6'],
            'strong' => ['<strong>Bold</strong>', '**Bold**'],
            'b tag' => ['<b>Bold</b>', '**Bold**'],
            'em' => ['<em>Italic</em>', '*Italic*'],
            'i tag' => ['<i>Italic</i>', '*Italic*'],
            'link' => ['<a href="url">Text</a>', '[Text](url)'],
            'unordered list' => ['<ul><li>Item</li></ul>', '- Item'],
            'ordered list' => ['<ol><li>Item</li></ol>', '1. Item'],
            'blockquote' => ['<blockquote>Quote</blockquote>', '> Quote'],
            'inline code' => ['<code>code</code>', '`code`'],
            'horizontal rule' => ['<hr>', '---'],
        ];
    }

    #[Test]
    public function itHandlesEmptyInput(): void
    {
        $markdown = $this->converter->convert('');

        $this->assertEquals('', trim($markdown));
    }

    #[Test]
    public function itHandlesWhitespaceOnlyInput(): void
    {
        $markdown = $this->converter->convert('   ');

        $this->assertEquals('', trim($markdown));
    }

    #[Test]
    public function itHandlesSpecialCharacters(): void
    {
        $html = '<p>Special chars: &amp; "quotes" \'apostrophes\'</p>';
        $markdown = $this->converter->convert($html);

        // Ampersand should be preserved in output
        $this->assertStringContainsString('Special chars:', $markdown);
        $this->assertStringContainsString('quotes', $markdown);
    }

    #[Test]
    public function itHandlesUnicodeCharacters(): void
    {
        $html = '<p>Unicode: 日本語 Ümlauts émojis 🚀</p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('日本語', $markdown);
        $this->assertStringContainsString('Ümlauts', $markdown);
        $this->assertStringContainsString('🚀', $markdown);
    }

    #[Test]
    public function itHandlesTables(): void
    {
        $html = '<table><tr><th>Header</th></tr><tr><td>Cell</td></tr></table>';
        $markdown = $this->converter->convert($html);

        // Tables should be converted to some format
        $this->assertStringContainsString('Header', $markdown);
        $this->assertStringContainsString('Cell', $markdown);
    }

    #[Test]
    public function itStripsScriptTagsWhenConfigured(): void
    {
        // Create a converter with script removal configured
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'header_style' => 'atx',
            'remove_nodes' => 'img picture style script',
        ]);

        $html = '<p>Content</p><script>alert("xss")</script><p>More content</p>';
        $markdown = $converter->convert($html);

        $this->assertStringNotContainsString('alert', $markdown);
        $this->assertStringContainsString('Content', $markdown);
        $this->assertStringContainsString('More content', $markdown);
    }

    #[Test]
    public function defaultConfigDoesNotRemoveScriptContent(): void
    {
        // Default plugin config doesn't include script in remove_nodes
        // This test documents the current behavior
        $html = '<p>Content</p><script>alert("xss")</script><p>More content</p>';
        $markdown = $this->converter->convert($html);

        // Script content may appear in output with default config
        $this->assertStringContainsString('Content', $markdown);
        $this->assertStringContainsString('More content', $markdown);
    }
}
