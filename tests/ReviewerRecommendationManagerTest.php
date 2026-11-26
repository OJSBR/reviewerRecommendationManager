<?php

/**
 * @file plugins/generic/reviewerRecommendationManager/tests/ReviewerRecommendationManagerTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRecommendationManagerTest
 *
 * @brief Reviewer form filtering, the recommendations a review may store, the
 *        settings check, stored labels, the override folders, the site level
 *        and the usage count.
 */

namespace APP\plugins\generic\reviewerRecommendationManager\tests;

use APP\plugins\generic\reviewerRecommendationManager\ReviewerRecommendationManagerPlugin;
use APP\plugins\generic\reviewerRecommendationManager\ReviewerRecommendationSettingsForm;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCustom;
use PKP\plugins\Hook;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\tests\PKPTestCase;

#[CoversClass(ReviewerRecommendationManagerPlugin::class)]
#[CoversClass(ReviewerRecommendationSettingsForm::class)]
class ReviewerRecommendationManagerTest extends PKPTestCase
{
    /** Codes 3 and 6 disabled in the journal. */
    protected function plugin(): ReviewerRecommendationManagerPlugin
    {
        return new class () extends ReviewerRecommendationManagerPlugin {
            public function isRecommendationEnabled(int $contextId, int $code): bool
            {
                return !in_array($code, [3, 6], true);
            }

            public function getRecommendationOrder(int $contextId, int $code): int
            {
                return [1 => 2, 2 => 1, 4 => 3][$code] ?? $code;
            }
        };
    }

    /** A request of journal 1, with the given user variables. */
    protected function request(array $vars = []): \PKP\core\PKPRequest
    {
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
        $request->_requestVars = $vars;
        return $request;
    }

    /** A reviewer form whose review assignment holds the given recommendation. */
    protected function reviewerForm(?int $stored): Form
    {
        $reviewAssignment = new ReviewAssignment();
        $reviewAssignment->setRecommendation($stored);
        return new class ($reviewAssignment) extends Form {
            public function __construct(private ReviewAssignment $reviewAssignment)
            {
                parent::__construct(null, false);
            }

            public function getReviewAssignment(): ReviewAssignment
            {
                return $this->reviewAssignment;
            }
        };
    }

    /** The custom check of a form that uses the given message key. */
    protected function checkWithMessage(Form $form, string $key): FormValidatorCustom
    {
        $checks = array_values(array_filter($form->_checks, fn ($check) => $check instanceof FormValidatorCustom && $check->_message === $key));
        $this->assertCount(1, $checks, "No check with the message {$key}.");
        return $checks[0];
    }

    public function testStoredLabelsCarryNoMarkupNorVueDelimiters(): void
    {
        $this->assertSame('Accept after minor revisions', ReviewerRecommendationManagerPlugin::sanitizeLabel("  Accept <b>after</b>\n minor revisions<script>alert(1)</script> "));
        $this->assertSame('{ {constructor} }', ReviewerRecommendationManagerPlugin::sanitizeLabel('{{constructor}}'));
        $this->assertSame('A & B', ReviewerRecommendationManagerPlugin::sanitizeLabel('A &amp; B'));
    }

    public function testOverlappingBracesKeepNoPairOfVueDelimiters(): void
    {
        $this->assertSame('{ { {x} } }', ReviewerRecommendationManagerPlugin::sanitizeLabel('{{{x}}}'));
        foreach (['{{{x}}}', '{{{{x}}}}', 'a{{b}}c{{{d}}}', '}}{{'] as $label) {
            $clean = ReviewerRecommendationManagerPlugin::sanitizeLabel($label);
            $this->assertFalse(str_contains($clean, '{{') || str_contains($clean, '}}'), "{$label} became {$clean}.");
        }
        $this->assertSame('{x}', ReviewerRecommendationManagerPlugin::sanitizeLabel('{x}'), 'A single brace was changed.');
    }

    public function testTheReviewerFormKeepsTheReviewsOwnRecommendationWhenItWasDisabled(): void
    {
        $this->request();
        $templateMgr = $this->templateManager(['' => 'Choose one', 1 => 'Accept', 2 => 'Minor', 3 => 'Resubmit', 4 => 'Decline']);

        // A draft saved with "Resubmit" (3), disabled afterwards: it is still offered to that review.
        $reviewAssignment = new ReviewAssignment();
        $reviewAssignment->setRecommendation(3);
        $templateMgr->vars['reviewAssignment'] = $reviewAssignment;
        $this->plugin()->filterReviewerForm('TemplateManager::fetch', [$templateMgr, 'reviewer/review/step3.tpl']);
        $this->assertSame(['' => 'Choose one', 2 => 'Minor', 1 => 'Accept', 3 => 'Resubmit', 4 => 'Decline'], $templateMgr->vars['reviewerRecommendationOptions']);

        // Another review, without a recommendation, does not get it.
        $templateMgr->vars['reviewerRecommendationOptions'] = ['' => 'Choose one', 1 => 'Accept', 3 => 'Resubmit'];
        $templateMgr->vars['reviewAssignment'] = new ReviewAssignment();
        $this->plugin()->filterReviewerForm('TemplateManager::fetch', [$templateMgr, 'reviewer/review/step3.tpl']);
        $this->assertSame(['' => 'Choose one', 1 => 'Accept'], $templateMgr->vars['reviewerRecommendationOptions']);
    }

    public function testARecommendationIsAllowedWhenEnabledOrAlreadyTheReviewsOwn(): void
    {
        $plugin = $this->plugin();
        $this->assertTrue($plugin->isRecommendationAllowed(1, '1', null));
        $this->assertFalse($plugin->isRecommendationAllowed(1, '3', null), 'A disabled code was allowed.');
        $this->assertFalse($plugin->isRecommendationAllowed(1, '3', 1), "A disabled code that is not the review's own was allowed.");
        $this->assertTrue($plugin->isRecommendationAllowed(1, '3', 3), "The review's own code was refused.");
        $this->assertFalse($plugin->isRecommendationAllowed(1, '99', null), 'An unknown code was allowed.');
        $this->assertFalse($plugin->isRecommendationAllowed(1, '1 OR 1', null), 'A code that is not a number was allowed.');
        $this->assertTrue($plugin->isRecommendationAllowed(1, '', null), 'An empty choice is the core\'s to refuse.');
    }

    public function testSubmittingADisabledRecommendationIsRefused(): void
    {
        $this->request();
        $plugin = $this->plugin();

        $form = $this->reviewerForm(null);
        $plugin->addRecommendationCheck('reviewerreviewstep3form::Constructor', [$form]);
        $check = $this->checkWithMessage($form, 'plugins.generic.reviewerRecommendationManager.recommendationUnavailable');
        $this->assertSame('recommendation', $check->getField());
        $form->setData('recommendation', '3');
        $this->assertFalse($check->isValid(), 'A disabled recommendation was accepted.');
        $form->setData('recommendation', '1');
        $this->assertTrue($check->isValid());

        $own = $this->reviewerForm(3);
        $plugin->addRecommendationCheck('reviewerreviewstep3form::Constructor', [$own]);
        $own->setData('recommendation', '3');
        $this->assertTrue($this->checkWithMessage($own, 'plugins.generic.reviewerRecommendationManager.recommendationUnavailable')->isValid(), "The review's own recommendation was refused.");
    }

    public function testADraftKeepsTheStoredRecommendationInsteadOfADisabledOne(): void
    {
        $plugin = $this->plugin();
        $vars = ['reviewFormResponses', 'comments', 'recommendation', 'commentsPrivate'];

        // "Save for Later" with a disabled code: the review keeps what it had.
        $this->request(['isSave' => '1', 'recommendation' => '6']);
        $form = $this->reviewerForm(2);
        $read = $vars;
        $plugin->keepDraftRecommendation('reviewerreviewstep3form::readUserVars', [$form, &$read]);
        $this->assertSame(['reviewFormResponses', 'comments', 'commentsPrivate'], $read);
        $this->assertSame(2, $form->getData('recommendation'));

        // An enabled code is read as sent, and a submit is left to the check.
        foreach ([['isSave' => '1', 'recommendation' => '4'], ['recommendation' => '6']] as $request) {
            $this->request($request);
            $read = $vars;
            $plugin->keepDraftRecommendation('reviewerreviewstep3form::readUserVars', [$this->reviewerForm(2), &$read]);
            $this->assertSame($vars, $read);
        }
    }

    public function testTheHooksAreTheOnesTheCoreFormCalls(): void
    {
        // The form names its hooks after its class: a wrong case in a hook name
        // leaves the check out without any error.
        $plugin = $this->plugin();
        $plugin->addReviewerFormHooks();
        try {
            $this->request(['isSave' => '1', 'recommendation' => '6', 'comments' => 'Draft']);
            $reviewAssignment = new ReviewAssignment();
            $reviewAssignment->setRecommendation(2);
            $form = new ReviewerReviewStep3Form($reviewAssignment);

            $this->checkWithMessage($form, 'plugins.generic.reviewerRecommendationManager.recommendationUnavailable');
            $form->readUserVars(['comments', 'recommendation']);
            $this->assertSame(2, $form->getData('recommendation'), 'The draft took a disabled recommendation.');
            $this->assertSame('Draft', $form->getData('comments'));
        } finally {
            Hook::clear(ReviewerRecommendationManagerPlugin::REVIEWER_STEP3_CONSTRUCTOR_HOOK);
            Hook::clear(ReviewerRecommendationManagerPlugin::REVIEWER_STEP3_READ_USER_VARS_HOOK);
        }
    }

    public function testTheSettingsCannotTurnEveryRecommendationOff(): void
    {
        $form = new ReviewerRecommendationSettingsForm($this->plugin(), 1);
        $check = $this->checkWithMessage($form, 'plugins.generic.reviewerRecommendationManager.settings.noneEnabled');

        foreach ([1, 2, 3, 4, 5, 6] as $code) {
            $form->setData("enabled_{$code}", null);
        }
        $this->assertFalse($check->isValid(), 'Every recommendation off was accepted.');

        $form->setData('enabled_5', '1');
        $this->assertTrue($check->isValid(), 'One recommendation on was refused.');
    }

    public function testOverridesOfLanguagesNoLongerInTheFormAreDeleted(): void
    {
        $base = sys_get_temp_dir() . '/rrm-' . bin2hex(random_bytes(4));
        foreach (['en', 'pt_BR', 'fr'] as $locale) {
            mkdir("{$base}/{$locale}", 0777, true);
            file_put_contents("{$base}/{$locale}/locale.po", "msgid \"\"\nmsgstr \"\"\n");
        }

        $this->assertSame(['fr'], ReviewerRecommendationManagerPlugin::pruneOverrideLocales($base, ['en', 'pt_BR', 'es']));
        $this->assertFalse(is_dir("{$base}/fr"), 'The folder of a removed language was kept.');
        $this->assertTrue(is_file("{$base}/en/locale.po") && is_file("{$base}/pt_BR/locale.po"), 'A form language lost its labels.');

        array_map('unlink', glob("{$base}/*/locale.po"));
        array_map('rmdir', glob("{$base}/*"));
        rmdir($base);
    }

    /** A template manager holding the given recommendation options. */
    protected function templateManager(array $options): object
    {
        return new class ($options) {
            public array $vars;

            public function __construct(array $options)
            {
                $this->vars = ['reviewerRecommendationOptions' => $options];
            }

            public function getTemplateVars($name)
            {
                return $this->vars[$name] ?? null;
            }

            public function assign($name, $value)
            {
                $this->vars[$name] = $value;
            }
        };
    }

    public function testTheReviewerFormKeepsChooseOneFirstDropsDisabledAndFollowsTheOrder(): void
    {
        $this->request();
        $plugin = $this->plugin();
        $templateMgr = $this->templateManager(['' => 'Choose one', 1 => 'Accept', 2 => 'Minor', 3 => 'Resubmit', 4 => 'Decline']);

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

    public function testTheSettingsTemplateLoadsItsOwnScript(): void
    {
        // Loaded by the page hook, the script was missing in a modal opened right after
        // the plugin was enabled, until the page was reloaded.
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/settingsForm.tpl');
        $this->assertSame(1, preg_match('#<script src="\{\$rrmScriptUrl\|escape\}"></script>.*window\.ojsbrReviewerRecommendationSettings\(#s', $template));
        $this->assertTrue(str_contains((string) file_get_contents(dirname(__DIR__) . '/ReviewerRecommendationSettingsForm.php'), "'/js/settingsForm.js'"));
        $this->assertFalse(str_contains((string) file_get_contents(dirname(__DIR__) . '/ReviewerRecommendationManagerPlugin.php'), 'TemplateManager::display'));
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
