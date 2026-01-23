<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\unit\twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use samuelreichor\llmify\twig\ExcludeLlmifyTokenParser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

#[CoversClass(ExcludeLlmifyTokenParser::class)]
class ExcludeLlmifyTokenParserTest extends TestCase
{
    private ExcludeLlmifyTokenParser $tokenParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenParser = new ExcludeLlmifyTokenParser();
    }

    #[Test]
    public function itExtendsAbstractTokenParser(): void
    {
        $this->assertInstanceOf(AbstractTokenParser::class, $this->tokenParser);
    }

    #[Test]
    public function getTagReturnsExcludeLlmify(): void
    {
        $this->assertEquals('excludeLlmify', $this->tokenParser->getTag());
    }

    #[Test]
    public function decideLlmifyEndReturnsTrueForEndexcludeLlmifyToken(): void
    {
        $token = new Token(Token::NAME_TYPE, 'endexcludeLlmify', 1);

        $result = $this->tokenParser->decideLlmifyEnd($token);

        $this->assertTrue($result);
    }

    #[Test]
    public function decideLlmifyEndReturnsFalseForOtherTokens(): void
    {
        $tokens = [
            new Token(Token::NAME_TYPE, 'excludeLlmify', 1),
            new Token(Token::NAME_TYPE, 'llmify', 1),
            new Token(Token::NAME_TYPE, 'endllmify', 1),
            new Token(Token::NAME_TYPE, 'if', 1),
            new Token(Token::NAME_TYPE, 'endif', 1),
            new Token(Token::NAME_TYPE, 'block', 1),
            new Token(Token::NAME_TYPE, 'endblock', 1),
        ];

        foreach ($tokens as $token) {
            $result = $this->tokenParser->decideLlmifyEnd($token);
            $this->assertFalse($result, "Token '{$token->getValue()}' should not end excludeLlmify block");
        }
    }

    #[Test]
    public function tagNameHasNoSpaces(): void
    {
        $tag = $this->tokenParser->getTag();

        $this->assertStringNotContainsString(' ', $tag, 'Tag name should not contain spaces');
    }

    #[Test]
    public function tagNameStartsWithExclude(): void
    {
        $tag = $this->tokenParser->getTag();

        $this->assertStringStartsWith('exclude', $tag, 'Tag should start with "exclude"');
    }

    #[Test]
    public function tagNameContainsLlmify(): void
    {
        $tag = $this->tokenParser->getTag();

        $this->assertStringContainsString('Llmify', $tag, 'Tag should contain "Llmify"');
    }
}
