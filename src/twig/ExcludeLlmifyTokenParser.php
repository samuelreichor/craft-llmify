<?php

namespace samuelreichor\llmify\twig;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class ExcludeLlmifyTokenParser extends AbstractTokenParser
{
    public function getTag(): string
    {
        return 'excludeLlmify';
    }

    public function parse(Token $token): ExcludeLlmifyNode
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideLlmifyEnd'], true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new ExcludeLlmifyNode($body, $lineno, $this->getTag());
    }

    public function decideLlmifyEnd(Token $token): bool
    {
        return $token->test('endexcludeLlmify');
    }
}
