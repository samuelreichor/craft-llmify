<?php

namespace samuelreichor\llmify;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Utilities;
use craft\web\UrlManager;
use samuelreichor\llmify\fields\LlmifySettingsField;
use samuelreichor\llmify\models\GlobalSettings;
use samuelreichor\llmify\services\HelperService;
use samuelreichor\llmify\services\LlmsService;
use samuelreichor\llmify\services\MarkdownService;
use samuelreichor\llmify\services\MetadataService;
use samuelreichor\llmify\services\SettingsService;
use samuelreichor\llmify\twig\LlmifyExtension;
use samuelreichor\llmify\utilities\Utils;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidRouteException;
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
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->_initLogger();
        $this->registerTwigExtension();
        $this->attachEventHandlers();

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
            'globals' => ['label' => 'Globals', 'url' => 'llmify/globals'],
            'content' => ['label' => 'Content', 'url' => 'llmify/content'],
        ];
        return $navItem;
    }

    protected function createSettingsModel(): ?Model
    {
        return new GlobalSettings();
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws Exception
     * @throws LoaderError
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate('llmify/settings/globals/index', [
            'settings' => $this->getSettings(),
        ]);
    }

    /**
     * @throws InvalidRouteException
     */
    public function getSettingsResponse(): Response
    {
        // Just redirect to the plugin settings page
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('llmify/globals'));
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

        // Listen for entries being saved
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                if (
                    $event->element instanceof Entry &&
                    !$event->isNew &&
                    !$event->element->isProvisionalDraft &&
                    !$event->element->isRevision
                ) {
                    $this->markdown->generateForEntry($event->element);
                }
            }
        );
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = Utils::class;
        });
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
