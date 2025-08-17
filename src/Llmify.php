<?php

namespace samuelreichor\llmify;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\enums\CmsEdition;
use craft\events\DefineHtmlEvent;
use craft\events\ElementEvent;
use craft\events\MultiElementActionEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\SectionEvent;
use craft\events\SiteEvent;
use craft\events\TemplateEvent;
use craft\services\Elements;
use craft\services\Entries;
use craft\services\Fields;
use craft\services\Sites;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\UrlManager;
use craft\web\View;
use samuelreichor\llmify\behaviors\ElementChangedBehavior;
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
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\Exception;
use yii\log\FileTarget;

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
    public bool $hasReadOnlyCpSettings = true;
    public bool $hasCpSection = true;
    private bool|null|PluginSettings $_settings;

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

        if (HelperService::isMarkdownCreationEnabled()) {
            $this->registerGeneralEvents();

            if (Craft::$app->request->getIsSiteRequest()) {
                $this->registerSiteEvents();
            }

            if (Craft::$app->request->getIsCpRequest()) {
                $this->registerElementChangeEvents();
            }

            Craft::$app->onAfterRequest(function() {
                $this->refresh->refresh();
            });
        }

        if (Craft::$app->request->getIsCpRequest()) {
            $this->registerSettingEvents();
            $this->registerGeneralCpEvents();

            if (Craft::$app->edition === CmsEdition::Pro) {
                $this->registerUserPermissionEvents();
            }
        }
    }

    /**
     * @throws Throwable
     */
    public function getCpNavItem(): ?array
    {
        $subNavs = [];
        $item = parent::getCpNavItem();
        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser->can(Constants::PERMISSION_EDIT_CONTENT)) {
            $subNavs['content'] = ['label' => 'Content', 'url' => 'llmify/content'];
        }

        if ($currentUser->can(Constants::PERMISSION_EDIT_SITE)) {
            $subNavs['globals'] = ['label' => 'Site', 'url' => 'llmify/globals'];
        }

        if (empty($subNavs)) {
            return null;
        }

        if (count($subNavs) <= 1) {
            return array_merge($item, [
                'subnav' => [],
            ]);
        }

        return array_merge($item, [
            'subnav' => $subNavs,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?PluginSettings
    {
        return new PluginSettings();
    }

    public function getSettings(): ?PluginSettings
    {
        if (!isset($this->_settings)) {
            $this->_settings = $this->createSettingsModel() ?: false;
        }

        return $this->_settings ?: null;
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
            'readOnly' => !Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ]);
    }

    private function registerSettingEvents(): void
    {
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
    }

    private function registerGeneralCpEvents(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = LlmifySettingsField::class;
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['llmify'] = 'llmify/content/redirect';
                $event->rules['llmify/content'] = 'llmify/content/index';
                $event->rules['llmify/content/<sectionId:\d+>'] = 'llmify/content/edit-section';
                $event->rules['llmify/content/save-section-settings'] = 'llmify/content/save-section-settings';
                $event->rules['llmify/globals'] = 'llmify/globals/index';
                $event->rules['llmify/globals/save-settings'] = 'llmify/globals/save-settings';
            }
        );

        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Utils::class;
        });

        Event::on(Entry::class, Entry::EVENT_DEFINE_SIDEBAR_HTML,
            function(DefineHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                $event->html .= $this->refresh->getSidebarHtml($entry);
            },
        );
    }

    private function registerGeneralEvents(): void
    {
        // Save Markdown for site requests triggered by the queue.
        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_TEMPLATE,
            function(TemplateEvent $event) {
                if ($event->templateMode !== 'site') {
                    return;
                }

                // Check if the request is coming from the queue job
                if (Craft::$app->request->getHeaders()->get(Constants::HEADER_REFRESH)) {
                    $markdownService = $this->markdown;
                    $html = $markdownService->getCombinedHtml();
                    if (!empty($html)) {
                        $markdownService->process($html);
                    }
                }

                // Always clear blocks to prevent memory leaks
                $this->markdown->clearBlocks();
            }
        );
    }

    private function registerElementChangeEvents(): void
    {
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
    }

    private function registerSiteEvents(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['llms.txt'] = 'llmify/file/generate-llms-txt';
                $event->rules['llms-full.txt'] = 'llmify/file/generate-llms-full-txt';

                $mdPrefix = $this->getSettings()->markdownUrlPrefix;
                $event->rules[$mdPrefix . '/<slug:.*\.md>'] = 'llmify/file/generate-page-md';
            }
        );
    }

    private function registerTwigExtension(): void
    {
        Craft::$app->view->registerTwigExtension(new LlmifyExtension());
    }

    private function registerUserPermissionEvents(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'LLMify',
                    'permissions' => [
                        Constants::PERMISSION_GENERATE => [
                            'label' => 'Generate Markdown',
                        ],
                        Constants::PERMISSION_CLEAR => [
                            'label' => 'Clear Markdown',
                        ],
                        Constants::PERMISSION_EDIT_CONTENT => [
                            'label' => 'Edit Content Settings',
                        ],
                        Constants::PERMISSION_EDIT_SITE => [
                            'label' => 'Edit Site Settings',
                        ],
                        Constants::PERMISSION_VIEW_SIDEBAR_PANEL => [
                            'label' => 'View sidebar panel on element edit pages',
                        ],
                    ],
                ];
            }
        );
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
