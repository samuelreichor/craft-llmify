<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\unit\models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use samuelreichor\llmify\models\PluginSettings;

#[CoversClass(PluginSettings::class)]
class PluginSettingsTest extends TestCase
{
    #[Test]
    public function itHasCorrectDefaultValues(): void
    {
        $settings = new PluginSettings();

        $this->assertTrue($settings->isEnabled);
        $this->assertFalse($settings->isRealUrlLlm);
        $this->assertEquals('raw', $settings->markdownUrlPrefix);
        $this->assertEquals(3, $settings->concurrentRequests);
        $this->assertEquals(100, $settings->requestTimeout);
    }

    #[Test]
    public function itHasCorrectDefaultMarkdownConfig(): void
    {
        $settings = new PluginSettings();

        $expectedConfig = [
            'strip_tags' => true,
            'header_style' => 'atx',
            'remove_nodes' => 'img picture style',
        ];

        $this->assertEquals($expectedConfig, $settings->markdownConfig);
    }

    #[Test]
    public function itHasCorrectDefaultExcludeClasses(): void
    {
        $settings = new PluginSettings();

        $expectedClasses = [
            ['classes' => 'exclude-llmify'],
        ];

        $this->assertEquals($expectedClasses, $settings->excludeClasses);
    }

    #[Test]
    public function itCanSetCustomValues(): void
    {
        $settings = new PluginSettings();
        $settings->isEnabled = false;
        $settings->markdownUrlPrefix = 'markdown';
        $settings->concurrentRequests = 10;
        $settings->requestTimeout = 200;

        $this->assertFalse($settings->isEnabled);
        $this->assertEquals('markdown', $settings->markdownUrlPrefix);
        $this->assertEquals(10, $settings->concurrentRequests);
        $this->assertEquals(200, $settings->requestTimeout);
    }

    #[Test]
    public function itCanSetCustomMarkdownConfig(): void
    {
        $settings = new PluginSettings();
        $settings->markdownConfig = [
            'strip_tags' => false,
            'header_style' => 'setext',
        ];

        $this->assertEquals([
            'strip_tags' => false,
            'header_style' => 'setext',
        ], $settings->markdownConfig);
    }

    #[Test]
    public function itCanSetMultipleExcludeClasses(): void
    {
        $settings = new PluginSettings();
        $settings->excludeClasses = [
            ['classes' => 'exclude-llmify'],
            ['classes' => 'no-index'],
            ['classes' => 'private-content'],
        ];

        $this->assertCount(3, $settings->excludeClasses);
    }

    #[Test]
    public function itDefinesValidationRules(): void
    {
        $settings = new PluginSettings();
        $rules = $settings->defineRules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    #[Test]
    public function validationRulesIncludeRequiredFields(): void
    {
        $settings = new PluginSettings();
        $rules = $settings->defineRules();

        // Find the required rule
        $requiredRule = null;
        foreach ($rules as $rule) {
            if (isset($rule[1]) && $rule[1] === 'required') {
                $requiredRule = $rule[0];
                break;
            }
        }

        $this->assertNotNull($requiredRule, 'Required rule should exist');
        $this->assertContains('markdownUrlPrefix', $requiredRule);
        $this->assertContains('concurrentRequests', $requiredRule);
        $this->assertContains('requestTimeout', $requiredRule);
    }

    #[Test]
    public function validationRulesIncludeConcurrentRequestsRange(): void
    {
        $settings = new PluginSettings();
        $rules = $settings->defineRules();

        // Find the integer rule for concurrentRequests
        $integerRule = null;
        foreach ($rules as $rule) {
            if (isset($rule[0]) && in_array('concurrentRequests', (array) $rule[0]) && isset($rule[1]) && $rule[1] === 'integer') {
                $integerRule = $rule;
                break;
            }
        }

        $this->assertNotNull($integerRule, 'Integer rule for concurrentRequests should exist');
        $this->assertEquals(1, $integerRule['min'] ?? null);
        $this->assertEquals(100, $integerRule['max'] ?? null);
    }

    #[Test]
    public function validationRulesIncludeRequestTimeoutMinimum(): void
    {
        $settings = new PluginSettings();
        $rules = $settings->defineRules();

        // Find the integer rule for requestTimeout
        $integerRule = null;
        foreach ($rules as $rule) {
            if (isset($rule[0]) && in_array('requestTimeout', (array) $rule[0]) && isset($rule[1]) && $rule[1] === 'integer') {
                $integerRule = $rule;
                break;
            }
        }

        $this->assertNotNull($integerRule, 'Integer rule for requestTimeout should exist');
        $this->assertEquals(1, $integerRule['min'] ?? null);
    }

    #[Test]
    #[DataProvider('provideValidConcurrentRequestValues')]
    public function concurrentRequestsAcceptsValidRange(int $value): void
    {
        $settings = new PluginSettings();
        $settings->concurrentRequests = $value;

        $this->assertEquals($value, $settings->concurrentRequests);
    }

    public static function provideValidConcurrentRequestValues(): array
    {
        return [
            'minimum value' => [1],
            'low value' => [5],
            'default value' => [3],
            'medium value' => [50],
            'maximum value' => [100],
        ];
    }

    #[Test]
    #[DataProvider('provideValidTimeoutValues')]
    public function requestTimeoutAcceptsValidValues(int $value): void
    {
        $settings = new PluginSettings();
        $settings->requestTimeout = $value;

        $this->assertEquals($value, $settings->requestTimeout);
    }

    public static function provideValidTimeoutValues(): array
    {
        return [
            'minimum value' => [1],
            'default value' => [100],
            'high value' => [500],
            'very high value' => [1000],
        ];
    }

    #[Test]
    #[DataProvider('provideValidUrlPrefixes')]
    public function markdownUrlPrefixAcceptsValidStrings(string $prefix): void
    {
        $settings = new PluginSettings();
        $settings->markdownUrlPrefix = $prefix;

        $this->assertEquals($prefix, $settings->markdownUrlPrefix);
    }

    public static function provideValidUrlPrefixes(): array
    {
        return [
            'default' => ['raw'],
            'markdown' => ['markdown'],
            'md' => ['md'],
            'content' => ['content'],
            'llm' => ['llm'],
        ];
    }
}
