<?php

namespace samuelreichor\llmify;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use samuelreichor\llmify\services\LlmifyService;
use samuelreichor\llmify\twig\LlmifyExtension;
use samuelreichor\llmify\models\Settings;
use yii\base\Event;

/**
 * llmify plugin
 *
 * @method static Llmify getInstance()
 * @method Settings getSettings()
 * @property-read LlmifyService $llmifyService
 * @author Samuel Reichör <samuelreichor@gmail.com>
 * @copyright Samuel Reichör
 * @license https://craftcms.github.io/license/ Craft License
 */
class Llmify extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'llmifyService' => LlmifyService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Add in our Twig extension
        Craft::$app->view->registerTwigExtension(new LlmifyExtension());

        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('llmify/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['llmify/cache/clear'] = 'llmify/cache/clear';
            }
        );
    }
}
