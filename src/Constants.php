<?php

namespace samuelreichor\llmify;

class Constants
{
    // Tables
    public const TABLE_PAGES = '{{%llmify_pages}}';
    public const TABLE_META = '{{%llmify_metadata}}';
    public const TABLE_GLOBALS = '{{%llmify_globals}}';

    // Permissions
    public const PERMISSION_GENERATE = 'llmify:generate';
    public const PERMISSION_CLEAR = 'llmify:clear';
    public const PERMISSION_VIEW_SIDEBAR_PANEL = 'llmify:view-sidebar-panel';
    public const PERMISSION_EDIT_CONTENT = 'llmify:edit-content';
    public const PERMISSION_EDIT_SITE = 'llmify:edit-site';

    // Random
    public const HEADER_REFRESH = 'X-Llmify-Refresh-Request';
}
