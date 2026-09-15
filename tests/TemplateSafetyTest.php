<?php

/**
 * @file plugins/generic/reviewerRecommendationManager/tests/TemplateSafetyTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemplateSafetyTest
 *
 * @brief Static checks on the settings template and the source files.
 */

namespace APP\plugins\generic\reviewerRecommendationManager\tests;

class TemplateSafetyTest extends TestCase
{
    protected function template(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/templates/settingsForm.tpl');
    }

    public function testFormIsProtectedAgainstCsrf(): void
    {
        $this->assertStringContainsString('{csrf}', $this->template());
        $this->assertStringContainsString('AjaxFormHandler', $this->template());
        $form = (string) file_get_contents(dirname(__DIR__) . '/ReviewerRecommendationSettingsForm.php');
        $this->assertStringContainsString('new FormValidatorCSRF($this)', $form);
        $this->assertStringContainsString('new FormValidatorPost($this)', $form);
    }

    public function testTemplateHasNoHardcodedText(): void
    {
        $text = preg_replace('/\{\*.*?\*\}/s', '', $this->template());
        $text = preg_replace('/<(script|style)\b.*?<\/\1>/s', '', $text);
        $text = preg_replace('/\{[^{}]*\}/', '', $text);
        $text = html_entity_decode(trim(strip_tags($text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Only the drag handle glyph is allowed.
        $this->assertSame('⠿', preg_replace('/\s+/u', '', $text), 'Visible text must come from locale keys.');
    }

    public function testStoredValuesAreEscapedInTheTemplate(): void
    {
        $template = $this->template();
        $this->assertStringContainsString('{$rec.original|escape}', $template);
        $this->assertStringContainsString('{$rec.order|escape}', $template);
        $this->assertSame(0, preg_match('/title="\{translate /', $template), 'A translation inside an attribute must be escaped.');
    }

    public function testTheDragHintMatchesTheHandle(): void
    {
        // The hint names the handle glyph; both must agree.
        $this->assertStringContainsString('&#10303;', $this->template());
        $en = (new PoFile(dirname(__DIR__) . '/locale/en/locale.po'))->entries['plugins.generic.reviewerRecommendationManager.settings.dragHint'];
        $this->assertStringContainsString('⠿', $en);
    }

    public function testSourceIsWrittenInEnglishWithTheStandardHeader(): void
    {
        $files = array_merge(glob(dirname(__DIR__) . '/*.php'), glob(__DIR__ . '/*.php'), glob(dirname(__DIR__) . '/templates/*.tpl'));
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $this->assertStringContainsString('Copyright (c) 2026 OJSBR (https://ojsbr.com)', $source, basename($file) . ' lacks the header.');
            $this->assertStringContainsString('plugins/generic/reviewerRecommendationManager/', $source, basename($file) . ' lacks the full @file path.');
            $this->assertSame(0, preg_match('/[ãõçáéíóú]/u', preg_replace(['/\'[^\']*\'/', '/"[^"]*"/'], '', $source)), basename($file) . ' has non-English text outside string literals.');
        }
    }
}
