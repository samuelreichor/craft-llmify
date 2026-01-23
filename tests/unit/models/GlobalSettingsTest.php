<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\unit\models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use samuelreichor\llmify\models\GlobalSettings;

#[CoversClass(GlobalSettings::class)]
class GlobalSettingsTest extends TestCase
{
    #[Test]
    public function itHasCorrectDefaultValues(): void
    {
        $settings = new GlobalSettings();

        $this->assertTrue($settings->enabled);
        $this->assertEquals('', $settings->llmTitle);
        $this->assertEquals('', $settings->llmDescription);
        $this->assertEquals('', $settings->llmNote);
    }

    #[Test]
    public function itCanBeInitializedWithConfig(): void
    {
        $config = [
            'siteId' => 1,
            'enabled' => true,
            'llmTitle' => 'My Website',
            'llmDescription' => 'A comprehensive guide to my website content',
            'llmNote' => 'Content updated daily',
        ];

        $settings = new GlobalSettings($config);

        $this->assertEquals(1, $settings->siteId);
        $this->assertTrue($settings->enabled);
        $this->assertEquals('My Website', $settings->llmTitle);
        $this->assertEquals('A comprehensive guide to my website content', $settings->llmDescription);
        $this->assertEquals('Content updated daily', $settings->llmNote);
    }

    #[Test]
    public function isEnabledReturnsTrueWhenEnabled(): void
    {
        $settings = new GlobalSettings();
        $settings->enabled = true;

        $this->assertTrue($settings->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalseWhenDisabled(): void
    {
        $settings = new GlobalSettings();
        $settings->enabled = false;

        $this->assertFalse($settings->isEnabled());
    }

    #[Test]
    public function itDefinesValidationRules(): void
    {
        $settings = new GlobalSettings();
        $rules = $settings->defineRules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    #[Test]
    public function validationRulesIncludeStringFields(): void
    {
        $settings = new GlobalSettings();
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
        $this->assertContains('llmTitle', $stringRule);
        $this->assertContains('llmDescription', $stringRule);
        $this->assertContains('llmNote', $stringRule);
    }

    #[Test]
    public function itCanHandleLongDescriptions(): void
    {
        $longDescription = str_repeat('This is a comprehensive description of the website. ', 50);

        $settings = new GlobalSettings();
        $settings->llmDescription = $longDescription;

        $this->assertEquals($longDescription, $settings->llmDescription);
    }

    #[Test]
    public function itCanHandleMultilineNote(): void
    {
        $multilineNote = <<<NOTE
Important information:
- Content is updated daily
- API access available
- Contact support for questions
NOTE;

        $settings = new GlobalSettings();
        $settings->llmNote = $multilineNote;

        $this->assertStringContainsString('Content is updated daily', $settings->llmNote);
        $this->assertStringContainsString('API access available', $settings->llmNote);
    }

    #[Test]
    #[DataProvider('provideSiteConfigurations')]
    public function itCanBeConfiguredForDifferentSites(int $siteId, string $title, string $description): void
    {
        $settings = new GlobalSettings([
            'siteId' => $siteId,
            'llmTitle' => $title,
            'llmDescription' => $description,
        ]);

        $this->assertEquals($siteId, $settings->siteId);
        $this->assertEquals($title, $settings->llmTitle);
        $this->assertEquals($description, $settings->llmDescription);
    }

    public static function provideSiteConfigurations(): array
    {
        return [
            'english site' => [1, 'My Website', 'English description'],
            'german site' => [2, 'Meine Website', 'Deutsche Beschreibung'],
            'french site' => [3, 'Mon Site Web', 'Description française'],
        ];
    }

    #[Test]
    public function defaultEnabledStateIsTrue(): void
    {
        $settings = new GlobalSettings();

        // By default, sites should be enabled
        $this->assertTrue($settings->enabled);
        $this->assertTrue($settings->isEnabled());
    }

    #[Test]
    public function itCanDisableSite(): void
    {
        $settings = new GlobalSettings([
            'siteId' => 1,
            'enabled' => false,
        ]);

        $this->assertFalse($settings->enabled);
        $this->assertFalse($settings->isEnabled());
    }

    #[Test]
    public function itCanHandleEmptyStrings(): void
    {
        $settings = new GlobalSettings([
            'siteId' => 1,
            'llmTitle' => '',
            'llmDescription' => '',
            'llmNote' => '',
        ]);

        $this->assertEquals('', $settings->llmTitle);
        $this->assertEquals('', $settings->llmDescription);
        $this->assertEquals('', $settings->llmNote);
    }

    #[Test]
    public function itCanHandleSpecialCharacters(): void
    {
        $settings = new GlobalSettings([
            'siteId' => 1,
            'llmTitle' => 'Website with "quotes" & <special> chars',
            'llmDescription' => 'Description with émojis 🚀 and ümlauts',
        ]);

        $this->assertEquals('Website with "quotes" & <special> chars', $settings->llmTitle);
        $this->assertEquals('Description with émojis 🚀 and ümlauts', $settings->llmDescription);
    }
}
