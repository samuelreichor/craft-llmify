<?php

namespace samuelreichor\llmify\services;

use Craft;
use craft\base\Component;
use craft\db\Query as DbQuery;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use samuelreichor\llmify\Constants;
use samuelreichor\llmify\Llmify;
use yii\base\InvalidConfigException;
use yii\db\Exception;

class DashboardService extends Component
{
    /**
     * Get site setup data for a single site.
     *
     * Returns check results and a score (0-100) for the site-level configuration.
     *
     * @return array{
     *     checks: array<int, array{label: string, ok: bool, detail: string}>,
     *     score: float,
     * }
     * @throws Exception
     */
    public function getSiteSetupData(int $siteId): array
    {
        $globalSettings = Llmify::getInstance()->settings->getGlobalSetting($siteId);

        $enabledFrontMatter = array_filter(
            $globalSettings->frontMatterFields,
            fn($field) => !empty($field['enabled']),
        );

        $checks = [
            [
                'label' => 'LLMify enabled',
                'ok' => $globalSettings->enabled,
                'detail' => '',
            ],
            [
                'label' => 'LLM Title',
                'ok' => $globalSettings->llmTitle !== '',
                'detail' => $globalSettings->llmTitle !== '' ? $this->truncate($globalSettings->llmTitle, 50) : '',
            ],
            [
                'label' => 'LLM Description',
                'ok' => $globalSettings->llmDescription !== '',
                'detail' => $globalSettings->llmDescription !== '' ? $this->truncate($globalSettings->llmDescription, 50) : '',
            ],
            [
                'label' => 'LLM Note',
                'ok' => $globalSettings->llmNote !== '',
                'detail' => $globalSettings->llmNote !== '' ? $this->truncate($globalSettings->llmNote, 50) : '',
            ],
            [
                'label' => 'Front Matter Fields',
                'ok' => count($enabledFrontMatter) > 0,
                'detail' => count($enabledFrontMatter) . '/' . count($globalSettings->frontMatterFields) . ' active',
            ],
        ];

        $passed = count(array_filter($checks, fn($c) => $c['ok']));
        $score = round(($passed / count($checks)) * 100);

        return [
            'checks' => $checks,
            'score' => $score,
        ];
    }

    /**
     * Get content setup data for a single site.
     *
     * Returns per-section check results and an overall score.
     *
     * @return array{
     *     sections: array<int, array{
     *         groupName: string,
     *         groupType: string,
     *         elementCount: int,
     *         editUrl: string,
     *         checks: array{enabled: bool, hasTitle: bool, hasDescription: bool, hasSectionTitle: bool, hasSectionDescription: bool},
     *     }>,
     *     score: float,
     * }
     * @throws InvalidConfigException
     */
    public function getContentSetupData(int $siteId): array
    {
        $contentSettings = Llmify::getInstance()->settings->getContentSettingsBySiteId($siteId);
        $sections = [];
        $totalChecks = 0;
        $passedChecks = 0;

        foreach ($contentSettings as $setting) {
            $groupName = null;
            $groupType = null;
            $elementCount = 0;

            if ($setting->elementType === Entry::class) {
                $section = Craft::$app->entries->getSectionById($setting->groupId);
                if ($section) {
                    $groupName = $section->name;
                    $groupType = ucfirst($section->type);
                }
            } elseif (HelperService::isCommerceInstalled() && $setting->elementType === \craft\commerce\elements\Product::class) {
                $productType = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeById($setting->groupId);
                if ($productType) {
                    $groupName = $productType->name;
                    $groupType = 'Product';
                }
            }

            if (!$groupName) {
                continue;
            }

            if ($setting->enabled) {
                $query = $setting->elementType === Entry::class
                    ? Entry::find()->sectionId($setting->groupId)->siteId($siteId)->status('enabled')
                    : \craft\commerce\elements\Product::find()->typeId($setting->groupId)->siteId($siteId)->status('enabled');
                $elementCount = (int)$query->count();
            }

            $hasTitle = $setting->llmTitleSource !== 'custom' || $setting->llmTitle !== '';
            $hasDescription = $setting->llmDescriptionSource !== 'custom' || $setting->llmDescription !== '';
            $hasSectionTitle = $setting->llmSectionTitle !== '';
            $hasSectionDescription = $setting->llmSectionDescription !== '';

            // Score: only count enabled sections
            if ($setting->enabled) {
                $sectionCheckCount = 4; // title, desc, sectionTitle, sectionDesc
                $sectionPassed = (int)$hasTitle + (int)$hasDescription + (int)$hasSectionTitle + (int)$hasSectionDescription;
                $totalChecks += $sectionCheckCount;
                $passedChecks += $sectionPassed;
            }

            $sections[] = [
                'groupName' => $groupName,
                'groupType' => $groupType,
                'elementCount' => $elementCount,
                'editUrl' => UrlHelper::cpUrl('llmify/content/' . $setting->groupId, ['elementType' => $setting->elementType]),
                'checks' => [
                    'enabled' => $setting->enabled,
                    'hasTitle' => $hasTitle,
                    'hasDescription' => $hasDescription,
                    'hasSectionTitle' => $hasSectionTitle,
                    'hasSectionDescription' => $hasSectionDescription,
                ],
            ];
        }

        $score = $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100) : 0;

        return [
            'sections' => $sections,
            'score' => $score,
        ];
    }

    /**
     * Get generation statistics for a single site.
     *
     * @return array{
     *     totalElements: int,
     *     totalPages: int,
     *     coveragePercent: float,
     *     lastRefresh: string|null,
     *     oldestPage: string|null,
     *     avgContentLength: int,
     *     avgTokens: int,
     * }
     */
    public function getGenerationStats(int $siteId): array
    {
        $contentSettings = Llmify::getInstance()->settings->getContentSettingsBySiteId($siteId);

        $totalElements = 0;
        foreach ($contentSettings as $setting) {
            if (!$setting->enabled) {
                continue;
            }

            if ($setting->elementType === Entry::class) {
                $totalElements += (int)Entry::find()
                    ->sectionId($setting->groupId)
                    ->siteId($siteId)
                    ->status('enabled')
                    ->count();
            } elseif (HelperService::isCommerceInstalled() && $setting->elementType === \craft\commerce\elements\Product::class) {
                $totalElements += (int)\craft\commerce\elements\Product::find()
                    ->typeId($setting->groupId)
                    ->siteId($siteId)
                    ->status('enabled')
                    ->count();
            }
        }

        $pageStats = (new DbQuery())
            ->select([
                'COUNT(*) as totalPages',
                'MAX([[dateUpdated]]) as lastRefresh',
                'MIN([[dateUpdated]]) as oldestPage',
                'AVG(LENGTH([[content]])) as avgContentLength',
            ])
            ->from([Constants::TABLE_PAGES])
            ->where(['siteId' => $siteId])
            ->one();

        $totalPages = (int)($pageStats['totalPages'] ?? 0);
        $coveragePercent = $totalElements > 0
            ? round(($totalPages / $totalElements) * 100, 1)
            : 0;

        return [
            'totalElements' => $totalElements,
            'totalPages' => $totalPages,
            'coveragePercent' => min($coveragePercent, 100),
            'lastRefresh' => $pageStats['lastRefresh'] ?? null,
            'oldestPage' => $pageStats['oldestPage'] ?? null,
            'avgContentLength' => (int)($pageStats['avgContentLength'] ?? 0),
            'avgTokens' => (int)(($pageStats['avgContentLength'] ?? 0) / 4),
        ];
    }

    /**
     * Get plugin settings checks for the top bar.
     *
     * @return array{
     *     isEnabled: bool,
     *     markdownUrlPrefix: string,
     *     botDetection: bool,
     *     discoveryTag: bool,
     *     autoServe: bool,
     *     excludeClasses: string[],
     * }
     */
    public function getPluginSettingsChecks(): array
    {
        $settings = Llmify::getInstance()->getSettings();

        $excludeClassNames = array_map(
            fn($item) => $item['classes'] ?? '',
            $settings->excludeClasses,
        );

        return [
            'isEnabled' => $settings->isEnabled,
            'markdownUrlPrefix' => $settings->markdownUrlPrefix,
            'botDetection' => $settings->enableBotDetection,
            'discoveryTag' => $settings->autoInjectDiscoveryTag,
            'autoServe' => $settings->autoServeMarkdown,
            'excludeClasses' => array_filter($excludeClassNames),
        ];
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }
}
