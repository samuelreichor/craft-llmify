<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;

use yii\caching\TagDependency;
use samuelreichor\llmify\Llmify;

class LlmifyService extends Component
{
    public const CACHE_TAG = 'llmify';

    public function process(string $html): void
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsCpRequest() || $request->getIsConsoleRequest()) {
            return;
        }

        $cacheKey = 'llmify_' . md5($request->getUrl());
        $settings = Llmify::getInstance()->getSettings();
        Craft::$app->getCache()->set($cacheKey, $html, $settings->cacheTtl, new TagDependency(['tags' => self::CACHE_TAG]));
        Craft::info($html, 'llmify');
    }
}
