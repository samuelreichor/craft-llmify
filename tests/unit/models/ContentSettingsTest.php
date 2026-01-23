<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\unit\models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use samuelreichor\llmify\models\ContentSettings;

#[CoversClass(ContentSettings::class)]
class ContentSettingsTest extends TestCase
{
    #[Test]
    public function itHasCorrectDefaultValues(): void
    {
        $settings = new ContentSettings();

        $this->assertNull($settings->id);
        $this->assertEquals('title', $settings->llmTitleSource);
        $this->assertEquals('', $settings->llmTitle);
        $this->assertEquals('custom', $settings->llmDescriptionSource);
        $this->assertEquals('', $settings->llmDescription);
        $this->assertEquals('', $settings->llmSectionTitle);
        $this->assertEquals('', $settings->llmSectionDescription);
        $this->assertFalse($settings->enabled);
    }

    #[Test]
    public function itCanBeInitializedWithConfig(): void
    {
        $config = [
            'id' => 1,
            'llmTitleSource' => 'seoTitle',
            'llmTitle' => 'Custom Title',
            'llmDescriptionSource' => 'metaDescription',
            'llmDescription' => 'Custom Description',
            'llmSectionTitle' => 'Blog Section',
            'llmSectionDescription' => 'All our blog posts',
            'sectionId' => 5,
            'entryTypeId' => 10,
            'siteId' => 1,
            'enabled' => true,
        ];

        $settings = new ContentSettings($config);

        $this->assertEquals(1, $settings->id);
        $this->assertEquals('seoTitle', $settings->llmTitleSource);
        $this->assertEquals('Custom Title', $settings->llmTitle);
        $this->assertEquals('metaDescription', $settings->llmDescriptionSource);
        $this->assertEquals('Custom Description', $settings->llmDescription);
        $this->assertEquals('Blog Section', $settings->llmSectionTitle);
        $this->assertEquals('All our blog posts', $settings->llmSectionDescription);
        $this->assertEquals(5, $settings->sectionId);
        $this->assertEquals(10, $settings->entryTypeId);
        $this->assertEquals(1, $settings->siteId);
        $this->assertTrue($settings->enabled);
    }

    #[Test]
    public function isEnabledReturnsTrueWhenEnabled(): void
    {
        $settings = new ContentSettings();
        $settings->enabled = true;

        $this->assertTrue($settings->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalseWhenDisabled(): void
    {
        $settings = new ContentSettings();
        $settings->enabled = false;

        $this->assertFalse($settings->isEnabled());
    }

    #[Test]
    public function itDefinesValidationRules(): void
    {
        $settings = new ContentSettings();
        $rules = $settings->defineRules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    #[Test]
    public function validationRulesIncludeStringFields(): void
    {
        $settings = new ContentSettings();
        $rules = $settings->defineRules();

        // Find the string rule
        $stringRule = null;
        foreach ($rules as $rule) {
            if (isset($rule[1]) && $rule[1] === 'string') {
                $stringRule = $rule[0];
                break;
            }
        }

        $this->assertNotNull($stringRule, 'String rule should exist');
        $this->assertContains('llmTitleSource', $stringRule);
        $this->assertContains('llmTitle', $stringRule);
        $this->assertContains('llmDescriptionSource', $stringRule);
        $this->assertContains('llmDescription', $stringRule);
        $this->assertContains('llmSectionTitle', $stringRule);
        $this->assertContains('llmSectionDescription', $stringRule);
    }

    #[Test]
    #[DataProvider('provideTitleSourceValues')]
    public function llmTitleSourceAcceptsValidValues(string $source): void
    {
        $settings = new ContentSettings();
        $settings->llmTitleSource = $source;

        $this->assertEquals($source, $settings->llmTitleSource);
    }

    public static function provideTitleSourceValues(): array
    {
        return [
            'title field' => ['title'],
            'custom' => ['custom'],
            'seo title' => ['seoTitle'],
            'meta title' => ['metaTitle'],
            'custom field handle' => ['myCustomField'],
        ];
    }

    #[Test]
    #[DataProvider('provideDescriptionSourceValues')]
    public function llmDescriptionSourceAcceptsValidValues(string $source): void
    {
        $settings = new ContentSettings();
        $settings->llmDescriptionSource = $source;

        $this->assertEquals($source, $settings->llmDescriptionSource);
    }

    public static function provideDescriptionSourceValues(): array
    {
        return [
            'custom' => ['custom'],
            'excerpt field' => ['excerpt'],
            'meta description' => ['metaDescription'],
            'summary' => ['summary'],
        ];
    }

    #[Test]
    public function itCanHandleLongSectionDescriptions(): void
    {
        $longDescription = str_repeat('This is a long description. ', 100);

        $settings = new ContentSettings();
        $settings->llmSectionDescription = $longDescription;

        $this->assertEquals($longDescription, $settings->llmSectionDescription);
    }

    #[Test]
    public function itCanBeConfiguredForDifferentSections(): void
    {
        $blogSettings = new ContentSettings([
            'sectionId' => 1,
            'entryTypeId' => 1,
            'siteId' => 1,
            'llmSectionTitle' => 'Blog',
            'enabled' => true,
        ]);

        $newsSettings = new ContentSettings([
            'sectionId' => 2,
            'entryTypeId' => 2,
            'siteId' => 1,
            'llmSectionTitle' => 'News',
            'enabled' => true,
        ]);

        $this->assertNotEquals($blogSettings->sectionId, $newsSettings->sectionId);
        $this->assertNotEquals($blogSettings->llmSectionTitle, $newsSettings->llmSectionTitle);
        $this->assertEquals($blogSettings->siteId, $newsSettings->siteId);
    }

    #[Test]
    public function itCanBeConfiguredForMultipleSites(): void
    {
        $englishSettings = new ContentSettings([
            'sectionId' => 1,
            'entryTypeId' => 1,
            'siteId' => 1,
            'llmSectionTitle' => 'Blog',
        ]);

        $germanSettings = new ContentSettings([
            'sectionId' => 1,
            'entryTypeId' => 1,
            'siteId' => 2,
            'llmSectionTitle' => 'Blog (DE)',
        ]);

        $this->assertEquals($englishSettings->sectionId, $germanSettings->sectionId);
        $this->assertNotEquals($englishSettings->siteId, $germanSettings->siteId);
        $this->assertNotEquals($englishSettings->llmSectionTitle, $germanSettings->llmSectionTitle);
    }
}
