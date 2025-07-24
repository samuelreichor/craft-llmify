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
            ->write("if (isset(\$context['url'])) { \$finalUrl = \$context['url']; }")
            ->write(" elseif (isset(\$context['entry']) && method_exists(\$context['entry'], 'getUrl')) { \$finalUrl = \$context['entry']->getUrl(); }")
            ->write(" else { \$finalUrl = \Craft::\$app->getRequest()->isSiteRequest() ? \Craft::\$app->getRequest()->getUrl() : null; }\n")

            ->write("if (\$finalUrl !== null) {")
            ->write("    \\samuelreichor\\llmify\\Llmify::getInstance()->markdown->process(\$llmifyBody, \$finalUrl);")
            ->write("}\n")
            ->write("echo \$llmifyBody;\n");
    }
}
