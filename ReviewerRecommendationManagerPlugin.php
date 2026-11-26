<?php

/**
 * @file plugins/generic/reviewerRecommendationManager/ReviewerRecommendationManagerPlugin.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRecommendationManagerPlugin
 *
 * @brief Lets a journal rename (multilingual), reorder and disable the reviewer
 *        recommendations without patching OJS core, preserving review history.
 */

namespace APP\plugins\generic\reviewerRecommendationManager;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use Gettext\Generator\PoGenerator;
use Gettext\Translation;
use Gettext\Translations;
use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\file\ContextFileManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCustom;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewerRecommendationManagerPlugin extends GenericPlugin
{
    /** Folder (inside the context files_dir) where the override .po files are written. */
    public const OVERRIDE_FOLDER = 'reviewerRecommendations';

    /** Reviewer step 3 template, where the recommendation list is built. */
    public const REVIEWER_STEP3_TEMPLATE = 'reviewer/review/step3.tpl';

    /**
     * Hooks of the reviewer step 3 form. The form names them after its class in
     * lower case; only the "Constructor" suffix keeps its capital letter.
     */
    public const REVIEWER_STEP3_CONSTRUCTOR_HOOK = 'reviewerreviewstep3form::Constructor';
    public const REVIEWER_STEP3_READ_USER_VARS_HOOK = 'reviewerreviewstep3form::readuservars';

    /**
     * Register the label overrides and the hooks that adjust the reviewer form.
     *
     * The plugin only acts on web pages of a journal, so the hooks are added
     * only when it is enabled there.
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isUnderMaintenance() || !$this->getEnabled($mainContextId)) {
            return true;
        }

        // 1) Rename: register the per-context override .po files with top priority.
        $this->registerLabelOverrides();

        // 2) Reorder / disable: adjust the list in the reviewer form only.
        Hook::add('TemplateManager::fetch', $this->filterReviewerForm(...));

        // 3) A disabled recommendation sent anyway (a form opened before it was
        //    disabled, or a crafted request) is refused on submit and not saved
        //    in a draft, unless it is the one the review already holds.
        $this->addReviewerFormHooks();

        return true;
    }

    /**
     * Hooks of the reviewer step 3 form that guard the recommendation it stores.
     */
    public function addReviewerFormHooks(): void
    {
        Hook::add(self::REVIEWER_STEP3_CONSTRUCTOR_HOOK, $this->addRecommendationCheck(...));
        Hook::add(self::REVIEWER_STEP3_READ_USER_VARS_HOOK, $this->keepDraftRecommendation(...));
    }

    /**
     * Register the current context's translation override folder, if it exists.
     * The folder is per context (ContextFileManager), so each journal only sees its own labels.
     */
    public function registerLabelOverrides(): void
    {
        $path = $this->getOverrideStoragePath(false);
        if ($path && is_dir($path)) {
            Locale::registerPath($path, PHP_INT_MAX);
        }
    }

    /**
     * TemplateManager::fetch hook: reorders and removes disabled options in the
     * variable passed to the reviewer step 3 template. It never touches the HTML,
     * only the variable before rendering, and does not affect the editor's view.
     */
    public function filterReviewerForm(string $hookName, array $args): bool
    {
        $templateMgr = $args[0];
        $template = $args[1];

        if ($template !== self::REVIEWER_STEP3_TEMPLATE) {
            return Hook::CONTINUE;
        }

        $options = $templateMgr->getTemplateVars('reviewerRecommendationOptions');
        if (!is_array($options)) {
            return Hook::CONTINUE;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return Hook::CONTINUE;
        }
        $contextId = $context->getId();

        // Always keep the "Choose One" entry ('') at the top.
        $chooseOne = [];
        if (array_key_exists('', $options)) {
            $chooseOne[''] = $options[''];
            unset($options['']);
        }

        // The review's own recommendation stays in the list even when it was
        // disabled later: a saved draft or a reopened review keeps showing it, and
        // saving again does not drop it.
        $reviewAssignment = $templateMgr->getTemplateVars('reviewAssignment');
        $stored = $reviewAssignment ? (int) $reviewAssignment->getRecommendation() : 0;

        // Drop disabled options and sort by the configured order.
        $codes = array_keys($options);
        $ordered = [];
        foreach ($codes as $code) {
            if ((int) $code !== $stored && !$this->isRecommendationEnabled($contextId, (int) $code)) {
                continue;
            }
            $ordered[$code] = [
                'label' => $options[$code],
                'order' => $this->getRecommendationOrder($contextId, (int) $code),
            ];
        }
        uasort($ordered, fn ($a, $b) => $a['order'] <=> $b['order']);

        $result = $chooseOne;
        foreach ($ordered as $code => $data) {
            $result[$code] = $data['label'];
        }

        $templateMgr->assign('reviewerRecommendationOptions', $result);
        return Hook::CONTINUE;
    }

    /**
     * Whether a recommendation sent by a reviewer may be stored: an enabled one,
     * or the one the review already holds. An empty choice is left to the core,
     * which requires a recommendation on submit.
     */
    public function isRecommendationAllowed(int $contextId, mixed $code, ?int $stored): bool
    {
        if ($code === null || $code === '' || (string) $code === '0') {
            return true;
        }
        if (!is_numeric($code) || (string) (int) $code !== (string) $code) {
            return false;
        }
        $code = (int) $code;
        if ($stored !== null && $code === $stored) {
            return true;
        }
        return array_key_exists($code, self::getRecommendationKeyMap()) && $this->isRecommendationEnabled($contextId, $code);
    }

    /**
     * reviewerreviewstep3form::Constructor hook: on submit, refuses a disabled
     * recommendation that is not the review's own.
     */
    public function addRecommendationCheck(string $hookName, array $args): bool
    {
        /** @var Form $form */
        $form = $args[0];
        $form->addCheck(new FormValidatorCustom(
            $form,
            'recommendation',
            'required',
            'plugins.generic.reviewerRecommendationManager.recommendationUnavailable',
            fn ($code) => $this->isRecommendationAllowed($this->getFormContextId($form), $code, $this->getStoredRecommendation($form))
        ));
        return Hook::CONTINUE;
    }

    /**
     * reviewerreviewstep3form::readUserVars hook: "Save for Later" does not
     * validate, so a disabled recommendation that is not the review's own is
     * replaced there by the one the review already holds.
     */
    public function keepDraftRecommendation(string $hookName, array $args): bool
    {
        $form = $args[0];
        $vars = &$args[1];
        $request = Application::get()->getRequest();
        if (!$request->getUserVar('isSave') || !in_array('recommendation', $vars, true)) {
            return Hook::CONTINUE;
        }

        $stored = $this->getStoredRecommendation($form);
        if (!$this->isRecommendationAllowed($this->getFormContextId($form), $request->getUserVar('recommendation'), $stored)) {
            $vars = array_values(array_diff($vars, ['recommendation']));
            $form->setData('recommendation', $stored);
        }
        return Hook::CONTINUE;
    }

    /**
     * The recommendation the review assignment of a reviewer form holds, if any.
     */
    protected function getStoredRecommendation(Form $form): ?int
    {
        $reviewAssignment = method_exists($form, 'getReviewAssignment') ? $form->getReviewAssignment() : null;
        $stored = $reviewAssignment ? $reviewAssignment->getRecommendation() : null;
        return $stored ? (int) $stored : null;
    }

    /**
     * The journal of a reviewer form: the one of the request.
     */
    protected function getFormContextId(Form $form): int
    {
        return (int) Application::get()->getRequest()->getContext()?->getId();
    }

    /**
     * Map of code => native locale key for the six recommendations (excluding '').
     *
     * @return array<int, string>
     */
    public static function getRecommendationKeyMap(): array
    {
        $options = ReviewAssignment::getReviewerRecommendationOptions();
        unset($options['']);
        return $options;
    }

    /**
     * The ORIGINAL OJS label for a key, ignoring this plugin's override.
     * Used as a fixed reference anchor on the settings screen (never renamed).
     */
    public function getOriginalLabel(string $key, ?string $locale = null): string
    {
        $locale ??= Locale::getLocale();
        $bundle = Locale::getBundle($locale, false);

        $storagePath = $this->getOverrideStoragePath(false);
        $storagePath = $storagePath ? realpath($storagePath) : false;
        if ($storagePath) {
            // Drop the plugin's override paths to recover the native translation.
            $entries = array_filter(
                $bundle->getEntries(),
                fn (string $path) => !str_starts_with($path, $storagePath),
                ARRAY_FILTER_USE_KEY
            );
            $bundle->setEntries($entries);
        }

        $value = $bundle->getTranslator()->getSingular($key);
        return ($value === null || $value === '') ? __($key, [], $locale) : $value;
    }

    /**
     * A label as it may be stored. The labels become translations of core keys
     * that the core prints without escaping (the reviewer grid builds its cell
     * HTML with them) and that also reach pages mounted by Vue, so markup is
     * removed and the Vue delimiters are broken apart.
     */
    public static function sanitizeLabel(?string $label): string
    {
        $label = html_entity_decode((string) $label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = strip_tags(preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $label) ?? '');
        // Every brace followed by another is split from it, so no run of braces
        // ({{x}}, {{{x}}}) keeps a pair of Vue delimiters.
        $label = preg_replace(['/\{(?=\{)/', '/\}(?=\})/'], ['{ ', '} '], $label) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $label) ?? '');
    }

    /**
     * Custom labels for a code (locale => text array), or [] when not configured.
     */
    public function getCustomLabels(int $contextId, int $code): array
    {
        $value = $this->getSetting($contextId, "label_{$code}");
        return is_array($value) ? $value : [];
    }

    public function isRecommendationEnabled(int $contextId, int $code): bool
    {
        $value = $this->getSetting($contextId, "enabled_{$code}");
        // No configuration means enabled by default (native behaviour).
        return $value === null ? true : (bool) $value;
    }

    public function getRecommendationOrder(int $contextId, int $code): int
    {
        $value = $this->getSetting($contextId, "order_{$code}");
        return $value === null || $value === '' ? $code : (int) $value;
    }

    /**
     * Path to the current context's translation override folder.
     *
     * @param bool $create Create the context base folder when it does not exist.
     */
    public function getOverrideStoragePath(bool $create = false): ?string
    {
        $context = Application::get()->getRequest()->getContext();
        if (!$context) {
            return null;
        }
        $fileManager = new ContextFileManager($context->getId());
        $path = $fileManager->getBasePath() . self::OVERRIDE_FOLDER;
        if ($create && !is_dir($path)) {
            $fileManager->mkdirtree($path);
        }
        return $path;
    }

    /**
     * Regenerate the override .po files from the configured labels.
     * A key is only written when a non-empty custom label exists for that locale;
     * otherwise the native OJS translation is kept.
     */
    public function regenerateOverrideFiles(int $contextId): void
    {
        $context = Application::get()->getRequest()->getContext();
        if (!$context) {
            return;
        }

        $basePath = $this->getOverrideStoragePath(true);
        $keyMap = self::getRecommendationKeyMap();
        $fileManager = new ContextFileManager($contextId);

        foreach ($context->getSupportedFormLocales() as $locale) {
            $filePath = "{$basePath}/{$locale}/locale.po";
            $translations = Translations::create(null, $locale);

            $hasEntries = false;
            foreach ($keyMap as $code => $localeKey) {
                $labels = $this->getCustomLabels($contextId, (int) $code);
                $value = isset($labels[$locale]) ? trim((string) $labels[$locale]) : '';
                if ($value === '') {
                    continue;
                }
                $translation = Translation::create('', $localeKey);
                $translation->translate($value);
                $translations->add($translation);
                $hasEntries = true;
            }

            if ($hasEntries) {
                if (!is_dir($dir = dirname($filePath))) {
                    $fileManager->mkdirtree($dir);
                }
                (new PoGenerator())->generateFile($translations, $filePath);
            } elseif (file_exists($filePath)) {
                $fileManager->deleteByPath($filePath);
            }
        }

        // A language removed from the journal's form languages leaves no override behind.
        self::pruneOverrideLocales($basePath, $context->getSupportedFormLocales());

        // Invalidate the locale bundle caches so the new labels apply in this request.
        Locale::registerPath($basePath, PHP_INT_MAX);
    }

    /**
     * Delete the override folders of languages that are no longer form
     * languages of the journal: the settings no longer show their labels, so
     * they could neither be seen nor changed, yet would keep renaming the
     * recommendations in that interface language.
     *
     * @param string[] $locales the form languages of the journal
     *
     * @return string[] the locales whose folder was deleted
     */
    public static function pruneOverrideLocales(string $basePath, array $locales): array
    {
        $removed = [];
        foreach (glob(rtrim($basePath, '/') . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $locale = basename($dir);
            if (in_array($locale, $locales, true)) {
                continue;
            }
            foreach (['locale.po', 'locale.mo'] as $file) {
                if (is_file("{$dir}/{$file}")) {
                    unlink("{$dir}/{$file}");
                }
            }
            if (@rmdir($dir)) {
                $removed[] = $locale;
            }
        }
        return $removed;
    }

    /**
     * Count of historical reviews per recommendation in this context (impact warning).
     *
     * @return array<int, int> recommendation code => number of reviews that used it
     */
    public static function getUsageCounts(int $contextId): array
    {
        $rows = Repo::reviewAssignment()->getCollector()
            ->filterByContextIds([$contextId])
            ->getQueryBuilder()
            ->whereNotNull('ra.recommendation')
            ->where('ra.recommendation', '!=', 0)
            ->groupBy('ra.recommendation')
            ->select('ra.recommendation')
            ->selectRaw('COUNT(*) AS cnt')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->recommendation] = (int) $row->cnt;
        }
        return $counts;
    }

    /**
     * Default settings installed for each new journal.
     */
    public function getContextSpecificPluginSettingsFile(): string
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Add the settings action to the plugin entry in the plugins list.
     */
    public function getActions($request, $verb): array
    {
        $actions = parent::getActions($request, $verb);
        if (!$request->getContext() || !$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        $url = $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']);
        array_unshift($actions, new LinkAction('settings', new AjaxModal($url, $this->getDisplayName()), __('manager.plugins.settings')));
        return $actions;
    }

    /**
     * Show and save the settings form of the journal.
     */
    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings' || !$request->getContext()) {
            return parent::manage($args, $request);
        }

        $form = new ReviewerRecommendationSettingsForm($this, $request->getContext()->getId());
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->execute();
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($request->getUser()->getId());
        return new JSONMessage(true);
    }

    /**
     * Name shown in the plugins list.
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.reviewerRecommendationManager.displayName');
    }

    /**
     * Description shown in the plugins list.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.reviewerRecommendationManager.description');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\reviewerRecommendationManager\ReviewerRecommendationManagerPlugin', '\ReviewerRecommendationManagerPlugin');
}
