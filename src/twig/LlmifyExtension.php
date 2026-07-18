<?php

namespace samuelreichor\llmify\twig;

use Craft;
use craft\base\ElementInterface;
use samuelreichor\llmify\Llmify;
use samuelreichor\llmify\services\HelperService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use yii\base\Exception;

class LlmifyExtension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [
            new LlmifyTokenParser(),
            new ExcludeLlmifyTokenParser(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('mdUrl', [$this, 'getMdUrl']),
            new TwigFunction('chatGptUrl', [$this, 'getChatGptUrl']),
            new TwigFunction('claudeUrl', [$this, 'getClaudeUrl']),
        ];
    }

    /**
     * Returns the markdown URL for the given element, or the currently
     * matched element when none is passed. Null when no element with a
     * URI can be resolved or the element is excluded from LLMify.
     *
     * @throws Exception
     */
    public function getMdUrl(?ElementInterface $element = null): ?string
    {
        if ($element === null) {
            $matched = Craft::$app->getUrlManager()->getMatchedElement();
            $element = $matched instanceof ElementInterface ? $matched : null;
        }

        if (!$element || !$element->uri) {
            return null;
        }

        // Read-only gating: pass triggerRefresh false so rendering a page that
        // uses these functions never enqueues a refresh as a side effect.
        if (!Llmify::getInstance()->refresh->canRefreshElement($element, false)) {
            return null;
        }

        return HelperService::getMarkdownUrl($element->uri, $element->siteId);
    }

    /**
     * Returns a link that opens ChatGPT with a prompt to read the
     * element's markdown URL.
     *
     * @throws Exception
     */
    public function getChatGptUrl(?ElementInterface $element = null): ?string
    {
        return $this->promptUrl('https://chatgpt.com/?hints=search&q=', $element);
    }

    /**
     * Returns a link that opens Claude with a prompt to read the
     * element's markdown URL.
     *
     * @throws Exception
     */
    public function getClaudeUrl(?ElementInterface $element = null): ?string
    {
        return $this->promptUrl('https://claude.ai/new?q=', $element);
    }

    /**
     * Builds a chat provider link that prompts the assistant to read the
     * element's markdown URL. Null when the element has no markdown URL.
     *
     * @throws Exception
     */
    private function promptUrl(string $base, ?ElementInterface $element): ?string
    {
        $mdUrl = $this->getMdUrl($element);

        if ($mdUrl === null) {
            return null;
        }

        return $base . rawurlencode("Read {$mdUrl} so I can ask questions about it.");
    }
}
