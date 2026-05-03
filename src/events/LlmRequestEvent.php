<?php

namespace samuelreichor\llmify\events;

use DateTime;
use samuelreichor\llmify\enums\LlmRequestType;
use yii\base\Event;

class LlmRequestEvent extends Event
{
    public LlmRequestType $requestType;
    public string $url;
    public string $userAgent = '';
    public ?string $botName = null;
    public bool $isBot = false;
    public ?int $siteId = null;
    public ?int $elementId = null;
    public ?string $elementType = null;
    public ?DateTime $timestamp = null;
}
