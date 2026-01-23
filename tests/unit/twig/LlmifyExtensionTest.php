<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\unit\twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use samuelreichor\llmify\twig\ExcludeLlmifyTokenParser;
use samuelreichor\llmify\twig\LlmifyExtension;
use samuelreichor\llmify\twig\LlmifyTokenParser;
use Twig\Extension\AbstractExtension;

#[CoversClass(LlmifyExtension::class)]
class LlmifyExtensionTest extends TestCase
{
    private LlmifyExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new LlmifyExtension();
    }

    #[Test]
    public function itExtendsAbstractExtension(): void
    {
        $this->assertInstanceOf(AbstractExtension::class, $this->extension);
    }

    #[Test]
    public function itReturnsTokenParsers(): void
    {
        $tokenParsers = $this->extension->getTokenParsers();

        $this->assertIsArray($tokenParsers);
        $this->assertNotEmpty($tokenParsers);
    }

    #[Test]
    public function itIncludesLlmifyTokenParser(): void
    {
        $tokenParsers = $this->extension->getTokenParsers();

        $hasLlmifyParser = false;
        foreach ($tokenParsers as $parser) {
            if ($parser instanceof LlmifyTokenParser) {
                $hasLlmifyParser = true;
                break;
            }
        }

        $this->assertTrue($hasLlmifyParser, 'LlmifyTokenParser should be included');
    }

    #[Test]
    public function itIncludesExcludeLlmifyTokenParser(): void
    {
        $tokenParsers = $this->extension->getTokenParsers();

        $hasExcludeParser = false;
        foreach ($tokenParsers as $parser) {
            if ($parser instanceof ExcludeLlmifyTokenParser) {
                $hasExcludeParser = true;
                break;
            }
        }

        $this->assertTrue($hasExcludeParser, 'ExcludeLlmifyTokenParser should be included');
    }

    #[Test]
    public function itReturnsTwoTokenParsers(): void
    {
        $tokenParsers = $this->extension->getTokenParsers();

        $this->assertCount(2, $tokenParsers);
    }
}
