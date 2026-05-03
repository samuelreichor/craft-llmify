<?php

namespace samuelreichor\llmify;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\enums\CmsEdition;
use craft\events\CancelableEvent;
use craft\events\DefineHtmlEvent;
use craft\events\ElementEvent;
use craft\events\MoveElementEvent;
use craft\events\MultiElementActionEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterPreviewTargetsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\SectionEvent;
use craft\events\SiteEvent;
use craft\events\TemplateEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Entries;
use craft\services\Fields;
use craft\services\Sites;
use craft\services\Structures;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\UrlManager;
use craft\web\View;
use putyourlightson\blitz\services\CacheRequestService;
use samuelreichor\llmify\behaviors\LlmifyChangedBehavior;
use samuelreichor\llmify\enums\LlmRequestType;
use samuelreichor\llmify\events\LlmRequestEvent;
use samuelreichor\llmify\fields\LlmifySettingsField;
use samuelreichor\llmify\models\PluginSettings;
use samuelreichor\llmify\services\BotDetectionService;
use samuelreichor\llmify\services\DashboardService;
use samuelreichor\llmify\services\FieldDiscoveryService;
use samuelreichor\llmify\services\FrontMatterService;
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
use yii\base\Event;
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
 * @property-read FieldDiscoveryService $fieldDiscovery
 * @property-read RefreshService $refresh
 * @property-read RequestService $request
 * @property-read FrontMatterService $frontMatter
 * @property-read BotDetectionService $botDetection
 * @property-read DashboardService $dashboard
 */
class Llmify extends Plugin
{
    /**
     * Fired when the plugin serves any LLM-targeted response: a `.md` page,
     * `llms.txt`, `llms-full.txt`, or a content-negotiated markdown response.
     *
     * @event LlmRequestEvent
     */
    public const EVENT_LLM_REQUEST = 'llmRequest';

    public string $schemaVersion = '1.1.0';
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
                'fieldDiscovery' => FieldDiscoveryService::class,
                'refresh' => RefreshService::class,
                'request' => RequestService::class,
                'frontMatter' => FrontMatterService::class,
                'botDetection' => BotDetectionService::class,
                'dashboard' => DashboardService::class,
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
                $this->registerAutoServeEvent();
                $this->registerDiscoveryLinkTag();
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
            $this->registerPreviewTargets();

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

        if ($currentUser->can(Constants::PERMISSION_VIEW_DASHBOARD)) {
            $subNavs['dashboard'] = ['label' => 'Dashboard', 'url' => 'llmify/dashboard'];
        }

        if ($currentUser->can(Constants::PERMISSION_EDIT_CONTENT)) {
            $subNavs['content'] = ['label' => 'Content', 'url' => 'llmify/content'];
        }

        if ($currentUser->can(Constants::PERMISSION_EDIT_SITE)) {
            $subNavs['globals'] = ['label' => 'Site', 'url' => 'llmify/globals'];
        }

        if ($currentUser->admin && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $subNavs['settings'] = ['label' => 'Settings', 'url' => 'llmify/settings'];
        }

        if (empty($subNavs)) {
            return null;
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

        if ($this->_settings instanceof PluginSettings) {
            $overrides = self::getTestOverrides();
            if (!empty($overrides['pluginSettings'])) {
                $this->_settings->setAttributes($overrides['pluginSettings'], false);
            }
        }

        return $this->_settings ?: null;
    }

    /**
     * Read test settings overrides from temp file (used by the llmify-testing module).
     * Returns null when no override file exists (production/normal usage).
     */
    public static function getTestOverrides(): ?array
    {
        static $loaded = false;
        static $data = null;

        if (!$loaded) {
            $loaded = true;
            $path = sys_get_temp_dir() . '/llmify-test-overrides.json';
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $data = $content ? json_decode($content, true) : null;
            }
        }

        return $data;
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('llmify/settings'));
    }

    /**
     * Fires `EVENT_LLM_REQUEST`. Skips listenerless calls so the request path
     * stays free when no consumer (e.g. Insights) is wired up.
     */
    public function fireLlmRequest(
        LlmRequestType $requestType,
        ?int $siteId = null,
        ?int $elementId = null,
        ?string $elementType = null,
        ?string $url = null,
    ): void {
        if (!$this->hasEventHandlers(self::EVENT_LLM_REQUEST)) {
            return;
        }

        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return;
        }

        $userAgent = (string)$request->getHeaders()->get('User-Agent', '');
        $botName = $userAgent !== '' ? $this->botDetection->getDetectedBotName($userAgent) : null;

        $event = new LlmRequestEvent();
        $event->requestType = $requestType;
        $event->url = $url ?? $request->getAbsoluteUrl();
        $event->userAgent = $userAgent;
        $event->botName = $botName;
        $event->isBot = $botName !== null;
        $event->siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $event->elementId = $elementId;
        $event->elementType = $elementType;
        $event->timestamp = new \DateTime();

        $this->trigger(self::EVENT_LLM_REQUEST, $event);
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
                    $this->settings->setContentSetting($sectionId, $siteId, Entry::class);
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
                    $this->settings->delContentSetting($sectionId, $siteId, Entry::class);
                }
            }
        );

        // Commerce Product Type events (guarded by Commerce existence)
        if (HelperService::isCommerceInstalled()) {
            Event::on(
                \craft\commerce\services\ProductTypes::class,
                \craft\commerce\services\ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE,
                function($event) {
                    $productType = $event->productType;
                    $allSiteIds = Craft::$app->getSites()->getAllSiteIds();
                    foreach ($allSiteIds as $siteId) {
                        $this->settings->setContentSetting($productType->id, $siteId, \craft\commerce\elements\Product::class);
                    }
                }
            );
        }

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
                $event->rules['llmify/dashboard'] = 'llmify/dashboard/index';
                $event->rules['llmify/content'] = 'llmify/content/index';
                $event->rules['llmify/content/<sectionId:\d+>'] = 'llmify/content/edit-section';
                $event->rules['llmify/content/save-section-settings'] = 'llmify/content/save-section-settings';
                $event->rules['llmify/globals'] = 'llmify/globals/index';
                $event->rules['llmify/globals/save-settings'] = 'llmify/globals/save-settings';
                $event->rules['llmify/settings'] = 'llmify/settings/index';
                $event->rules['llmify/settings/save-settings'] = 'llmify/settings/save-settings';
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

        // Commerce Product sidebar (guarded by Commerce existence)
        if (HelperService::isCommerceInstalled()) {
            Event::on(\craft\commerce\elements\Product::class, \craft\commerce\elements\Product::EVENT_DEFINE_SIDEBAR_HTML,
                function(DefineHtmlEvent $event) {
                    $product = $event->sender;
                    $event->html .= $this->refresh->getSidebarHtml($product);
                },
            );
        }
    }

    private function registerPreviewTargets(): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_PREVIEW_TARGETS,
            static function(RegisterPreviewTargetsEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                if ($entry->uri !== null) {
                    $event->previewTargets[] = [
                        'label' => '📄 ' . Craft::t('llmify', 'Markdown Preview'),
                        'url' => HelperService::getMarkdownUrl($entry->uri, $entry->siteId),
                    ];
                }
            }
        );

        if (HelperService::isCommerceInstalled()) {
            Event::on(
                \craft\commerce\elements\Product::class,
                Element::EVENT_REGISTER_PREVIEW_TARGETS,
                static function(RegisterPreviewTargetsEvent $event) {
                    $product = $event->sender;
                    if ($product->uri !== null) {
                        $event->previewTargets[] = [
                            'label' => '📄 ' . Craft::t('llmify', 'Markdown Preview'),
                            'url' => HelperService::getMarkdownUrl($product->uri, $product->siteId),
                        ];
                    }
                }
            );
        }
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

                // Only web requests have headers
                if (Craft::$app->request->getIsConsoleRequest()) {
                    return;
                }

                $headers = Craft::$app->request->getHeaders();

                // Check if the request is coming from the queue job
                if ($headers->get(Constants::HEADER_REFRESH)) {
                    $this->markdown->processContentBlocks();
                } elseif ($this->isAutoServeAble()) {
                    // Fallback: generate on-the-fly when no cached markdown exists
                    // (early auto-serve in init() handles the cached case)
                    $markdown = $this->markdown->resolveAutoServeMarkdown();

                    if ($markdown !== null) {
                        $event->output = $markdown;
                        $response = Craft::$app->response;
                        $response->format = $response::FORMAT_RAW;
                        $response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');
                        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
                        $response->headers->set('Vary', 'Accept, User-Agent');

                        $element = Craft::$app->getUrlManager()->getMatchedElement();
                        $this->fireLlmRequest(
                            LlmRequestType::Negotiated,
                            elementId: $element ? $element->id : null,
                            elementType: $element ? get_class($element) : null,
                        );
                    }
                }

                // Always clear blocks to prevent memory leaks
                $this->markdown->clearBlocks();
            }
        );
    }

    /**
     * Tell Blitz to skip caching for text/markdown or bot requests so our template event can handle them.
     */
    private function registerAutoServeEvent(): void
    {
        if (Craft::$app->request->getIsConsoleRequest()) {
            return;
        }

        if (!$this->isAutoServeAble()) {
            return;
        }

        // Tell Blitz (or other caching plugins) to not serve from cache
        if (class_exists(CacheRequestService::class)) {
            Event::on(CacheRequestService::class, CacheRequestService::EVENT_IS_CACHEABLE_REQUEST,
                function(CancelableEvent $event) {
                    $event->isValid = false;
                }
            );
        }
    }

    private function isAutoServeAble(): bool
    {
        if (Craft::$app->request->getIsConsoleRequest()) {
            return false;
        }

        $settings = $this->getSettings();

        $accept = Craft::$app->request->getHeaders()->get('Accept', '');
        if ($settings->autoServeMarkdown && str_contains($accept, 'text/markdown')) {
            return true;
        }

        if ($settings->enableBotDetection) {
            $userAgent = Craft::$app->request->getHeaders()->get('User-Agent', '');
            if ($this->botDetection->isAiBot($userAgent)) {
                return true;
            }
        }

        return false;
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
                        $element->attachBehavior(LlmifyChangedBehavior::BEHAVIOR_NAME, LlmifyChangedBehavior::class);
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

        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT,
            function(MoveElementEvent $event) {
                $this->refresh->addElement($event->element);
            }
        );
    }

    private function registerSiteEvents(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['llms.txt'] = 'llmify/file/generate-llms-txt';
                $event->rules['.well-known/llms.txt'] = 'llmify/file/generate-llms-txt';
                $event->rules['llms-full.txt'] = 'llmify/file/generate-llms-full-txt';

                $mdPrefix = $this->getSettings()->markdownUrlPrefix;
                $mdRoute = $mdPrefix !== '' ? $mdPrefix . '/<slug:.*\.md>' : '<slug:.*\.md>';
                $event->rules[$mdRoute] = 'llmify/file/generate-page-md';
            }
        );
    }

    private function registerDiscoveryLinkTag(): void
    {
        if (!$this->getSettings()->autoInjectDiscoveryTag) {
            return;
        }

        $registered = false;

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function(TemplateEvent $event) use (&$registered) {
                if ($registered || $event->templateMode !== 'site') {
                    return;
                }

                $registered = true;

                /** @var UrlManager $urlManager */
                $urlManager = Craft::$app->getUrlManager();
                $element = $urlManager->getMatchedElement();

                if (!$element || !$element->uri) {
                    return;
                }

                if ($this->refresh->canRefreshElement($element)) {
                    $markdownUrl = HelperService::getMarkdownUrl($element->uri, $element->siteId);
                    Craft::$app->view->registerLinkTag([
                        'rel' => 'alternate',
                        'type' => 'text/markdown',
                        'href' => $markdownUrl,
                    ], 'llmify-alternate');
                }
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
                        Constants::PERMISSION_VIEW_DASHBOARD => [
                            'label' => 'View Dashboard',
                        ],
                        Constants::PERMISSION_EDIT_CONTENT => [
                            'label' => 'Edit Content Settings',
                        ],
                        Constants::PERMISSION_EDIT_SITE => [
                            'label' => 'Edit Site Settings',
                        ],
                        Constants::PERMISSION_GENERATE => [
                            'label' => 'Generate Markdown',
                        ],
                        Constants::PERMISSION_CLEAR => [
                            'label' => 'Clear Markdown',
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
