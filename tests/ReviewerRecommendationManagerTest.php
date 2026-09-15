<?php

/**
 * @file plugins/generic/reviewerRecommendationManager/tests/ReviewerRecommendationManagerTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRecommendationManagerTest
 *
 * @brief Reviewer form filtering, stored labels, the site level and the usage count.
 */

namespace APP\plugins\generic\reviewerRecommendationManager\tests;

use APP\plugins\generic\reviewerRecommendationManager\ReviewerRecommendationManagerPlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\tests\PKPTestCase;

#[CoversClass(ReviewerRecommendationManagerPlugin::class)]
class ReviewerRecommendationManagerTest extends PKPTestCase
{
    public function testStoredLabelsCarryNoMarkupNorVueDelimiters(): void
    {
        $this->assertSame('Accept after minor revisions', ReviewerRecommendationManagerPlugin::sanitizeLabel("  Accept <b>after</b>\n minor revisions<script>alert(1)</script> "));
        $this->assertSame('{ {constructor} }', ReviewerRecommendationManagerPlugin::sanitizeLabel('{{constructor}}'));
        $this->assertSame('A & B', ReviewerRecommendationManagerPlugin::sanitizeLabel('A &amp; B'));
    }

    public function testTheReviewerFormKeepsChooseOneFirstDropsDisabledAndFollowsTheOrder(): void
    {
        $plugin = new class () extends ReviewerRecommendationManagerPlugin {
            public function isRecommendationEnabled(int $contextId, int $code): bool
            {
                return $code !== 3;
            }

            public function getRecommendationOrder(int $contextId, int $code): int
            {
                return [1 => 2, 2 => 1, 4 => 3][$code] ?? $code;
            }
        };
        $templateMgr = new class () {
            public array $vars = ['reviewerRecommendationOptions' => ['' => 'Choose one', 1 => 'Accept', 2 => 'Minor', 3 => 'Resubmit', 4 => 'Decline']];

            public function getTemplateVars($name)
            {
                return $this->vars[$name] ?? null;
            }

            public function assign($name, $value)
            {
                $this->vars[$name] = $value;
            }
        };
        $request = \APP\core\Application::get()->getRequest();
        $router = new class () extends \APP\core\PageRouter {
            public function getContext($request, $forceReload = false)
            {
                $context = new \APP\journal\Journal();
                $context->setId(1);
                return $context;
            }
        };
        $router->setApplication(\APP\core\Application::get());
        $request->setRouter($router);

        $plugin->filterReviewerForm('TemplateManager::fetch', [$templateMgr, 'reviewer/review/step3.tpl']);
        $this->assertSame(['' => 'Choose one', 2 => 'Minor', 1 => 'Accept', 4 => 'Decline'], $templateMgr->vars['reviewerRecommendationOptions']);

        $templateMgr->vars['reviewerRecommendationOptions'] = ['' => 'x', 1 => 'Accept'];
        $plugin->filterReviewerForm('TemplateManager::fetch', [$templateMgr, 'reviewer/review/step1.tpl']);
        $this->assertSame(['' => 'x', 1 => 'Accept'], $templateMgr->vars['reviewerRecommendationOptions'], 'Another template was changed.');
    }

    public function testTheSiteLevelHasNoSettingsAction(): void
    {
        $request = new class () {
            public function getContext()
            {
                return null;
            }

            public function getRouter()
            {
                throw new \RuntimeException('The site level must not build a settings URL.');
            }
        };
        $plugin = new class () extends ReviewerRecommendationManagerPlugin {
            public function getEnabled($contextId = null)
            {
                return true;
            }
        };
        $this->assertSame([], array_filter($plugin->getActions($request, []), fn ($action) => $action->getId() === 'settings'));
    }

    public function testUsageIsCountedThroughTheReviewAssignmentCollector(): void
    {
        $counts = ReviewerRecommendationManagerPlugin::getUsageCounts(1);
        $this->assertTrue(is_array($counts));
        foreach ($counts as $code => $count) {
            $this->assertTrue(is_int($code) && is_int($count) && $count > 0);
        }
        $source = (string) file_get_contents(dirname(__DIR__) . '/ReviewerRecommendationManagerPlugin.php');
        $this->assertTrue(strpos($source, "DB::table('review_assignments AS ra')") !== false);
    }

    public function testTheSettingsTemplateOnlyInitialisesTheForm(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/settingsForm.tpl');
        $this->assertFalse(stripos($template, '<style') !== false, 'The template prints a style block.');
        preg_match_all('#<script>(.*?)</script>#s', $template, $m);
        $this->assertSame(1, count($m[1]));
        $this->assertTrue(strlen(trim($m[1][0])) < 260, 'The template script does more than initialise the form.');
    }
}
