<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\unit\twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use samuelreichor\llmify\twig\LlmifyTokenParser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

#[CoversClass(LlmifyTokenParser::class)]
class LlmifyTokenParserTest extends TestCase
{
    private LlmifyTokenParser $tokenParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenParser = new LlmifyTokenParser();
    }

    #[Test]
    public function itExtendsAbstractTokenParser(): void
    {
        $this->assertInstanceOf(AbstractTokenParser::class, $this->tokenParser);
    }

    #[Test]
    public function getTagReturnsLlmify(): void
    {
        $this->assertEquals('llmify', $this->tokenParser->getTag());
    }

    #[Test]
    public function decideLlmifyEndReturnsTrueForEndllmifyToken(): void
    {
        $token = new Token(Token::NAME_TYPE, 'endllmify', 1);

        $result = $this->tokenParser->decideLlmifyEnd($token);

        $this->assertTrue($result);
    }

    #[Test]
    public function decideLlmifyEndReturnsFalseForOtherTokens(): void
    {
        $tokens = [
            new Token(Token::NAME_TYPE, 'llmify', 1),
            new Token(Token::NAME_TYPE, 'if', 1),
            new Token(Token::NAME_TYPE, 'endif', 1),
            new Token(Token::NAME_TYPE, 'block', 1),
            new Token(Token::NAME_TYPE, 'endblock', 1),
            new Token(Token::NAME_TYPE, 'for', 1),
            new Token(Token::NAME_TYPE, 'endfor', 1),
        ];

        foreach ($tokens as $token) {
            $result = $this->tokenParser->decideLlmifyEnd($token);
            $this->assertFalse($result, "Token '{$token->getValue()}' should not end llmify block");
        }
    }

    #[Test]
    public function tagNameIsLowercase(): void
    {
        $tag = $this->tokenParser->getTag();

        $this->assertEquals(strtolower($tag), $tag, 'Tag name should be lowercase');
    }

    #[Test]
    public function tagNameHasNoSpaces(): void
    {
        $tag = $this->tokenParser->getTag();

        $this->assertStringNotContainsString(' ', $tag, 'Tag name should not contain spaces');
    }
}
