<?php

namespace samuelreichor\llmify\enums;

enum LlmRequestType: string
{
    case Direct = 'direct';
    case Negotiated = 'negotiated';
}
