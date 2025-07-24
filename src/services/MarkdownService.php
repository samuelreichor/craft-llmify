<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use samuelreichor\llmify\Constants;
use yii\base\InvalidConfigException;
use yii\caching\TagDependency;
use samuelreichor\llmify\Llmify;

class MarkdownService extends Component
{
    /**
     * @throws InvalidConfigException
     */
    public function process(string $html): void
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsCpRequest() || $request->getIsConsoleRequest()) {
            return;
        }

        $cacheKey = 'llmify_' . md5($request->getUrl());
        $settings = Llmify::getInstance()->getSettings();
        Craft::$app->getCache()->set($cacheKey, $html, $settings->cacheTtl, new TagDependency(['tags' => Constants::CACHE_TAG_GLOBAL]));
        Craft::info($html, 'llmify');
    }
}
