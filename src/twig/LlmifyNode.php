<?php

namespace samuelreichor\llmify\twig;

use Twig\Compiler;
use Twig\Node\Node;

class LlmifyNode extends Node
{
    public function __construct(Node $body, int $lineno, string $tag)
    {
        parent::__construct(['body' => $body], [], $lineno, $tag);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write("ob_start();\n")
            ->subcompile($this->getNode('body'))
            ->write("\$llmifyBody = ob_get_clean();\n")
            ->write("\\samuelreichor\\llmify\\Llmify::getInstance()->markdown->process(\$llmifyBody);\n")
            ->write("echo \$llmifyBody;\n");
    }
}
