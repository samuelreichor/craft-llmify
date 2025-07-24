<?php

namespace samuelreichor\llmify\twig;

use Twig\Extension\AbstractExtension;

class LlmifyExtension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [
            new LlmifyTokenParser(),
        ];
    }
}
