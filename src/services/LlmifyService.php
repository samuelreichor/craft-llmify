<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;

use samuelreichor\llmify\Llmify;

class LlmifyService extends Component
{
    public function process(string $html): void
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsCpRequest() || $request->getIsConsoleRequest()) {
            return;
        }

        $cacheKey = 'llmify_' . md5($request->getUrl());
        $settings = Llmify::getInstance()->getSettings();
        Craft::$app->getCache()->set($cacheKey, $html, $settings->cacheTtl);
        Craft::info($html, 'llmify');
    }
}
