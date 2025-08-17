<?php

namespace samuelreichor\llmify\twig;

use Twig\Compiler;
use Twig\Node\Node;

class ExcludeLlmifyNode extends Node
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
            ->write("\$llmifyExcludedBody = ob_get_clean();\n")
            ->write("if (isset(\$context['entry'], \$context['entry']->id, \$context['entry']->siteId)) {\n")
            ->write("    \$entryId = \$context['entry']->id;\n")
            ->write("    \$siteId = \$context['entry']->siteId;\n")
            ->write("    \samuelreichor\\llmify\\Llmify::getInstance()->markdown->addExcludedContentBlock(\$llmifyExcludedBody, \$entryId, \$siteId);\n")
            ->write("}\n")
            ->write("echo \$llmifyExcludedBody;\n");
    }
}
