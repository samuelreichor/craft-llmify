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
            ->write("\$llmifyElement = \$context['product'] ?? \$context['entry'] ?? null;\n")
            ->write("if (isset(\$llmifyElement, \$llmifyElement->id, \$llmifyElement->siteId)) {\n")
            ->write("    \$entryId = \$llmifyElement->id;\n")
            ->write("    \$siteId = \$llmifyElement->siteId;\n")
            ->write("    \\samuelreichor\\llmify\\Llmify::getInstance()->markdown->addContentBlock(\$llmifyBody, \$entryId, \$siteId);\n")
            ->write("}\n")
            ->write("echo \$llmifyBody;\n");
    }
}
