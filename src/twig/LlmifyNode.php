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

            // This is the robust way to get the URL.
            // It checks for local variables that Twig creates from the context.
            ->write("if (isset(\$context['entry'], \$context['entry']->id, \$context['entry']->siteId)) {\n")
            ->write("    \$entryId = \$context['entry']->id;\n")
            ->write("    \$siteId = \$context['entry']->siteId;\n")
            ->write("\n")
            ->write("    // Rufe die process-Methode mit entryId und siteId auf.\n")
            ->write("    \\samuelreichor\\llmify\\Llmify::getInstance()->markdown->process(\$llmifyBody, \$entryId, \$siteId);\n")
            ->write("}\n")
            ->write("echo \$llmifyBody;\n");
    }
}
