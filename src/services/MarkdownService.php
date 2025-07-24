<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use samuelreichor\llmify\Constants;
use yii\base\InvalidConfigException;
use yii\caching\TagDependency;
use samuelreichor\llmify\Llmify;
use League\HTMLToMarkdown\HtmlConverter;

class MarkdownService extends Component
{
    /**
     * @throws InvalidConfigException
     */
    public function process(string $html, string $url = null): void
    {
        if ($url === null) {
            $request = Craft::$app->getRequest();
            if ($request->getIsCpRequest() || $request->getIsConsoleRequest()) {
                Craft::error('The page didn`t process because no url was provided.' . $request->getUrl(), 'llmify');
                return;
            }
            $url = $request->getAbsoluteUrl();
        }

        $cacheKey = 'llmify_' . md5($url);
        $settings = Llmify::getInstance()->getSettings();
        $markdown = $this->htmlToMarkdown($html);
        Craft::$app->getCache()->set($cacheKey, $markdown, $settings->cacheTtl, new TagDependency(['tags' => Constants::CACHE_TAG_GLOBAL]));
    }

    /**
     * Generates and processes HTML for a specific entry.
     *
     * @param Entry $entry
     * @throws SiteNotFoundException
     */
    public function generateForEntry(Entry $entry): void
    {
        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;
        $templatePath = $entry->section->getSiteSettings()[$currentSiteId]->template;

        try {
            $view = Craft::$app->getView();
            $originalMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
            $view->renderTemplate($templatePath, [
                'entry' => $entry,
                'url' => $entry->getUrl(),
            ]);

            $view->setTemplateMode($originalMode);
        } catch (\Throwable $e) {
            Craft::error('Failed to render template for llmify: ' . $e->getMessage(), 'llmify');
        }
    }

    public function htmlToMarkdown(string $html): string
    {
        $converter = new HtmlConverter();
        $converter->getConfig()->setOption('strip_tags', true);
        $converter->getConfig()->setOption('header_style', 'atx');
        return $converter->convert($html);
    }
}
