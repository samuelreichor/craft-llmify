<?php

namespace samuelreichor\llmify\events;

use yii\base\Event;

/**
 * Fired when the WebMCP tool set is built, before the registration script is
 * generated. Listeners can append custom tools, tweak the native definitions,
 * or remove tools entirely. A tool appended with the name of an existing tool
 * replaces it.
 *
 * Each tool is an array with:
 *
 *  - `name`         The unique tool name, e.g. `book_appointment`.
 *  - `description`  What the tool does, shown to the browser agent.
 *  - `inputSchema`  JSON Schema describing the tool input.
 *  - `handler`      Client-side JS function source executed when the agent
 *                   calls the tool: `async (input, helpers) => result`.
 *                   `helpers` provides `text(value)` to build a text result,
 *                   `fetchTool(url)` for same-origin JSON fetches, and
 *                   `config`. Native tools have built-in handlers and don't
 *                   need this key.
 *
 * ```php
 * use samuelreichor\llmify\events\RegisterWebMcpToolsEvent;
 * use samuelreichor\llmify\services\WebMcpService;
 * use yii\base\Event;
 *
 * Event::on(WebMcpService::class, WebMcpService::EVENT_REGISTER_TOOLS,
 *     function(RegisterWebMcpToolsEvent $event) {
 *         $event->tools[] = [
 *             'name' => 'get_opening_hours',
 *             'description' => 'Get the opening hours of the store.',
 *             'inputSchema' => ['type' => 'object'],
 *             'handler' => <<<JS
 * async (input, helpers) => {
 *   const r = await helpers.fetchTool(new URL('/api/opening-hours', location.origin));
 *   return helpers.text(r.ok ? JSON.stringify(r.data) : 'Opening hours are unavailable.');
 * }
 * JS,
 *         ];
 *     }
 * );
 * ```
 *
 * @author Samuel Reichör <samuelreichor@gmail.com>
 */
class RegisterWebMcpToolsEvent extends Event
{
    /**
     * @var array<int, array{name?: string, description?: string, inputSchema?: array<string, mixed>, handler?: string}>
     */
    public array $tools = [];
}
