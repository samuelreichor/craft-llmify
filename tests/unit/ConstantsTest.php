<?php

declare(strict_types=1);

namespace samuelreichor\llmify\tests\unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use samuelreichor\llmify\Constants;

#[CoversClass(Constants::class)]
class ConstantsTest extends TestCase
{
    #[Test]
    public function tableConstantsHaveCorrectCraftFormat(): void
    {
        // Craft CMS uses {{%tablename}} format for table prefixes
        $this->assertMatchesRegularExpression('/^\{\{%[\w]+\}\}$/', Constants::TABLE_PAGES);
        $this->assertMatchesRegularExpression('/^\{\{%[\w]+\}\}$/', Constants::TABLE_META);
        $this->assertMatchesRegularExpression('/^\{\{%[\w]+\}\}$/', Constants::TABLE_GLOBALS);
    }

    #[Test]
    public function tablePagesHasCorrectValue(): void
    {
        $this->assertEquals('{{%llmify_pages}}', Constants::TABLE_PAGES);
    }

    #[Test]
    public function tableMetaHasCorrectValue(): void
    {
        $this->assertEquals('{{%llmify_metadata}}', Constants::TABLE_META);
    }

    #[Test]
    public function tableGlobalsHasCorrectValue(): void
    {
        $this->assertEquals('{{%llmify_globals}}', Constants::TABLE_GLOBALS);
    }

    #[Test]
    public function permissionConstantsHaveCorrectFormat(): void
    {
        // Permissions should follow the format 'plugin:action'
        $this->assertMatchesRegularExpression('/^llmify:[\w-]+$/', Constants::PERMISSION_GENERATE);
        $this->assertMatchesRegularExpression('/^llmify:[\w-]+$/', Constants::PERMISSION_CLEAR);
        $this->assertMatchesRegularExpression('/^llmify:[\w-]+$/', Constants::PERMISSION_VIEW_SIDEBAR_PANEL);
        $this->assertMatchesRegularExpression('/^llmify:[\w-]+$/', Constants::PERMISSION_EDIT_CONTENT);
        $this->assertMatchesRegularExpression('/^llmify:[\w-]+$/', Constants::PERMISSION_EDIT_SITE);
    }

    #[Test]
    public function permissionGenerateHasCorrectValue(): void
    {
        $this->assertEquals('llmify:generate', Constants::PERMISSION_GENERATE);
    }

    #[Test]
    public function permissionClearHasCorrectValue(): void
    {
        $this->assertEquals('llmify:clear', Constants::PERMISSION_CLEAR);
    }

    #[Test]
    public function permissionViewSidebarPanelHasCorrectValue(): void
    {
        $this->assertEquals('llmify:view-sidebar-panel', Constants::PERMISSION_VIEW_SIDEBAR_PANEL);
    }

    #[Test]
    public function permissionEditContentHasCorrectValue(): void
    {
        $this->assertEquals('llmify:edit-content', Constants::PERMISSION_EDIT_CONTENT);
    }

    #[Test]
    public function permissionEditSiteHasCorrectValue(): void
    {
        $this->assertEquals('llmify:edit-site', Constants::PERMISSION_EDIT_SITE);
    }

    #[Test]
    public function headerRefreshHasCorrectValue(): void
    {
        $this->assertEquals('X-Llmify-Refresh-Request', Constants::HEADER_REFRESH);
    }

    #[Test]
    public function headerRefreshFollowsHttpHeaderConvention(): void
    {
        // HTTP headers typically use X- prefix for custom headers and PascalCase
        $this->assertStringStartsWith('X-', Constants::HEADER_REFRESH);
    }

    #[Test]
    public function allTableConstantsStartWithLlmifyPrefix(): void
    {
        $this->assertStringContainsString('llmify_', Constants::TABLE_PAGES);
        $this->assertStringContainsString('llmify_', Constants::TABLE_META);
        $this->assertStringContainsString('llmify_', Constants::TABLE_GLOBALS);
    }

    #[Test]
    public function allPermissionConstantsStartWithLlmifyPrefix(): void
    {
        $this->assertStringStartsWith('llmify:', Constants::PERMISSION_GENERATE);
        $this->assertStringStartsWith('llmify:', Constants::PERMISSION_CLEAR);
        $this->assertStringStartsWith('llmify:', Constants::PERMISSION_VIEW_SIDEBAR_PANEL);
        $this->assertStringStartsWith('llmify:', Constants::PERMISSION_EDIT_CONTENT);
        $this->assertStringStartsWith('llmify:', Constants::PERMISSION_EDIT_SITE);
    }

    #[Test]
    public function constantsAreUnique(): void
    {
        $tables = [
            Constants::TABLE_PAGES,
            Constants::TABLE_META,
            Constants::TABLE_GLOBALS,
        ];

        $permissions = [
            Constants::PERMISSION_GENERATE,
            Constants::PERMISSION_CLEAR,
            Constants::PERMISSION_VIEW_SIDEBAR_PANEL,
            Constants::PERMISSION_EDIT_CONTENT,
            Constants::PERMISSION_EDIT_SITE,
        ];

        // Tables should be unique
        $this->assertCount(count($tables), array_unique($tables), 'Table constants should be unique');

        // Permissions should be unique
        $this->assertCount(count($permissions), array_unique($permissions), 'Permission constants should be unique');
    }
}
