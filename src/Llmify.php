<?php

namespace samuelreichor\llmify;

use Craft;
use craft\base\Element;
use samuelreichor\llmify\behaviors\ElementChangedBehavior;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\events\MultiElementActionEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\SectionEvent;
use craft\events\SiteEvent;
use craft\events\TemplateEvent;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Entries;
use craft\services\Fields;
use craft\services\Sites;
use craft\services\Utilities;
use craft\web\UrlManager;
use craft\web\View;
use samuelreichor\llmify\fields\LlmifySettingsField;
use samuelreichor\llmify\models\PluginSettings;
use samuelreichor\llmify\services\HelperService;
use samuelreichor\llmify\services\LlmsService;
use samuelreichor\llmify\services\MarkdownService;
use samuelreichor\llmify\services\MetadataService;
use samuelreichor\llmify\services\RefreshService;
use samuelreichor\llmify\services\RequestService;
use samuelreichor\llmify\services\SettingsService;
use samuelreichor\llmify\twig\LlmifyExtension;
use samuelreichor\llmify\utilities\Utils;
use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;
use yii\base\ViewEvent;
use yii\log\FileTarget;
use yii\web\Response;

/**
 * llmify plugin
 *
 * @method static Llmify getInstance()
 * @author Samuel Reichör <samuelreichor@gmail.com>
 * @copyright Samuel Reichör
 * @license https://craftcms.github.io/license/ Craft License
 *
 * @property-read MarkdownService $markdown
 * @property-read LlmsService $llms
 * @property-read SettingsService $settings
 * @property-read MetadataService $metadata
 * @property-read HelperService $helper
 * @property-read RefreshService $refresh
 * @property-read RequestService $request
 */
class Llmify extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'markdown' => MarkdownService::class,
                'llms' => LlmsService::class,
                'settings' => SettingsService::class,
                'metadata' => MetadataService::class,
                'helper' => HelperService::class,
                'refresh' => RefreshService::class,
                'request' => RequestService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->_initLogger();
        $this->registerTwigExtension();
        $this->attachEventHandlers();

        Craft::$app->onAfterRequest(function() {
            $this->refresh->refresh();
        });
        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
    }

    public function getCpNavItem(): ?array
    {
        $navItem = parent::getCpNavItem();
        $navItem['subnav'] = [
            'globals' => ['label' => 'Site Settings', 'url' => 'llmify/globals'],
            'content' => ['label' => 'Content', 'url' => 'llmify/content'],
        ];
        return $navItem;
    }

    /**
     * @throws InvalidConfigException
     */
    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(PluginSettings::class);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws Exception
     * @throws LoaderError
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate('llmify/settings/plugin/index', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = LlmifySettingsField::class;
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['llmify/cache/clear'] = 'llmify/cache/clear';
                $event->rules['llmify'] = 'llmify/globals/redirect';
                $event->rules['llmify/globals'] = 'llmify/globals/index';
                $event->rules['llmify/globals/save-settings'] = 'llmify/globals/save-settings';
                $event->rules['llmify/content'] = 'llmify/content/index';
                $event->rules['llmify/content/<sectionId:\d+>'] = 'llmify/content/edit-section';
                $event->rules['llmify/content/save-section-settings'] = 'llmify/content/save-section-settings';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['llms.txt'] = 'llmify/file/generate-llms-txt';
                $event->rules['llms-full.txt'] = 'llmify/file/generate-llms-full-txt';
                $event->rules['raw/<slug:.*\.md>'] = 'llmify/file/generate-page-md';
            }
        );

        // Create Content Settings for new Sections
        Event::on(
            Entries::class,
            Entries::EVENT_AFTER_SAVE_SECTION,
            function(SectionEvent $event) {
                $section = $event->section;
                $sectionId = $section->id;
                $siteIds = $section->getSiteIds();
                foreach ($siteIds as $siteId) {
                    $this->settings->setContentSetting($sectionId, $siteId);
                }
            }
        );

        // Delete Content Settings if Section gets deleted
        Event::on(
            Entries::class,
            Entries::EVENT_AFTER_DELETE_SECTION,
            function(SectionEvent $event) {
                $section = $event->section;
                $sectionId = $section->id;
                $siteIds = $section->getSiteIds();
                foreach ($siteIds as $siteId) {
                    $this->settings->delContentSetting($sectionId, $siteId);
                }
            }
        );

        // Create Global Settings for new Sites
        Event::on(
            Sites::class,
            Sites::EVENT_AFTER_SAVE_SITE,
            function(SiteEvent $event) {
                $siteId = $event->site->id;
                $this->settings->setGlobalSetting($siteId);
            }
        );

        // Delete Global Settings if Site gets deleted
        Event::on(
            Sites::class,
            Sites::EVENT_AFTER_DELETE_SITE,
            function(SiteEvent $event) {
                $siteId = $event->site->id;
                $this->settings->delGlobalSetting($siteId);
            }
        );

        // Set the previous status of an element so we can compare later
        $events = [
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            Elements::EVENT_BEFORE_RESAVE_ELEMENT,
            Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI,
            Elements::EVENT_BEFORE_DELETE_ELEMENT,
            Elements::EVENT_BEFORE_RESTORE_ELEMENT,
        ];

        foreach ($events as $event) {
            Event::on(Elements::class, $event,
                function(ElementEvent|MultiElementActionEvent $event) {
                    /** @var Element $element */
                    $element = $event->element;
                    if ($this->refresh->isRefreshableElement($element)) {
                        $element->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);
                    }
                }
            );
        }

        $events = [
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            Elements::EVENT_AFTER_RESAVE_ELEMENT,
            Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            Elements::EVENT_AFTER_RESTORE_ELEMENT,
        ];

        foreach ($events as $event) {
            Event::on(Elements::class, $event,
                function(ElementEvent|MultiElementActionEvent $event) {
                    $this->refresh->addElement($event->element);
                }
            );
        }

        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = Utils::class;
        });

        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_TEMPLATE,
            function(TemplateEvent $event) {
                $markdownService = $this->markdown;
                $html = $markdownService->getCombinedHtml();
                if (!empty($html)) {
                    $markdownService->process($html);
                }
                $markdownService->clearBlocks();
            }
        );
    }

    private function registerTwigExtension(): void
    {
        Craft::$app->view->registerTwigExtension(new LlmifyExtension());
    }

    private function _initLogger(): void
    {
        $logFileTarget = new FileTarget([
            'logFile' => '@storage/logs/llmify.log',
            'maxLogFiles' => 10,
            'categories' => ['llmify'],
            'logVars' => [],
        ]);
        Craft::getLogger()->dispatcher->targets[] = $logFileTarget;
    }
}
