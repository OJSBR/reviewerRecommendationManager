<?php

/**
 * @file plugins/generic/reviewerRecommendationManager/tests/PluginTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginTest
 *
 * @brief The plugin classes compiled against the PKP classes of the
 *        installation, and the rules the settings rest on.
 */

namespace APP\plugins\generic\reviewerRecommendationManager\tests;

use APP\plugins\generic\reviewerRecommendationManager\ReviewerRecommendationManagerPlugin;
use APP\plugins\generic\reviewerRecommendationManager\ReviewerRecommendationSettingsForm;
use PKP\submission\reviewAssignment\ReviewAssignment;
use ReflectionClass;
use ReflectionNamedType;

class PluginTest extends TestCase
{
    public function testOverriddenMethodsDeclareTheReturnTypesOfThisPkpVersion(): void
    {
        // A missing or different return type on an override is a fatal error
        // that php -l does not catch: it only shows next to the parent class.
        $this->assertTrue(is_subclass_of(ReviewerRecommendationManagerPlugin::class, \PKP\plugins\GenericPlugin::class));
        $this->assertTrue(is_subclass_of(ReviewerRecommendationSettingsForm::class, \PKP\form\Form::class));
        foreach ([ReviewerRecommendationManagerPlugin::class, ReviewerRecommendationSettingsForm::class] as $class) {
            $reflection = new ReflectionClass($class);
            $parent = $reflection->getParentClass();
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class || !$parent->hasMethod($method->getName())) {
                    continue;
                }
                $parentType = $parent->getMethod($method->getName())->getReturnType();
                if ($parentType === null) {
                    continue;
                }
                $type = $method->getReturnType();
                $this->assertTrue(
                    $type !== null && ((string) $type === (string) $parentType || ($type instanceof ReflectionNamedType && '?' . $type->getName() === (string) $parentType)),
                    sprintf('%s::%s() must declare a return type compatible with %s.', $reflection->getShortName(), $method->getName(), $parentType)
                );
            }
        }
    }

    public function testTheSixCoreRecommendationsAreManaged(): void
    {
        $map = ReviewerRecommendationManagerPlugin::getRecommendationKeyMap();

        $this->assertCount(6, $map);
        $this->assertFalse(array_key_exists('', $map), 'The "Choose One" entry is not a recommendation.');
        $this->assertSame(ReviewAssignment::getReviewerRecommendationOptions()[ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT], $map[ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT]);
        foreach ($map as $code => $key) {
            $this->assertTrue(is_int($code) && $code > 0, 'Unexpected recommendation code ' . var_export($code, true));
            $this->assertSame(1, preg_match('/^[a-z.]+$/i', $key), "Unexpected locale key {$key}.");
        }
    }

    public function testLabelsAreStrippedOfMarkupAndVueDelimiters(): void
    {
        $this->assertSame('Accept as is', ReviewerRecommendationManagerPlugin::sanitizeLabel('  Accept   as is '));
        $this->assertSame('Accept', ReviewerRecommendationManagerPlugin::sanitizeLabel('<b>Accept</b><script>alert(1)</script>'));
        $this->assertSame('Accept', ReviewerRecommendationManagerPlugin::sanitizeLabel('&lt;img src=x onerror=alert(1)&gt;Accept'));
        $this->assertSame('Accept { {7*7} }', ReviewerRecommendationManagerPlugin::sanitizeLabel('Accept {{7*7}}'));
        $this->assertSame('Aceitar com revisões & ajustes', ReviewerRecommendationManagerPlugin::sanitizeLabel('Aceitar com revisões &amp; ajustes'));
        $this->assertSame('', ReviewerRecommendationManagerPlugin::sanitizeLabel(null));
    }

    public function testTheFormSavesSanitizedLabels(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/ReviewerRecommendationSettingsForm.php');
        $this->assertStringContainsString('ReviewerRecommendationManagerPlugin::sanitizeLabel(', $source);
        $this->assertStringContainsString("updateSetting(\$this->contextId, \"label_{\$code}\", \$labels, 'object')", $source);
    }

    public function testOnlyTheReviewerFormIsFiltered(): void
    {
        $this->assertSame('reviewer/review/step3.tpl', ReviewerRecommendationManagerPlugin::REVIEWER_STEP3_TEMPLATE);
        $source = (string) file_get_contents(dirname(__DIR__) . '/ReviewerRecommendationManagerPlugin.php');
        $this->assertStringNotContainsString('TemplateResource::getFilename', $source, 'No core template may be replaced.');
        $this->assertStringNotContainsString('clearTemplateCache', $source);
    }
}
