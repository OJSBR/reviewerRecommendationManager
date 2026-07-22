<?php

/**
 * @file ReviewerRecommendationManagerPlugin.php
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
use APP\notification\NotificationManager;
use Gettext\Generator\PoGenerator;
use Gettext\Translation;
use Gettext\Translations;
use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\file\ContextFileManager;
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
     * @copydoc Plugin::register()
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
        Hook::add('TemplateManager::fetch', [$this, 'filterReviewerForm']);

        return true;
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

        // Drop disabled options and sort by the configured order.
        $codes = array_keys($options);
        $ordered = [];
        foreach ($codes as $code) {
            if (!$this->isRecommendationEnabled($contextId, (int) $code)) {
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

        // Invalidate the locale bundle caches so the new labels apply in this request.
        Locale::registerPath($basePath, PHP_INT_MAX);
    }

    /**
     * Count of historical reviews per recommendation in this context (impact warning).
     *
     * @return array<int, int> código => número de pareceres já emitidos
     */
    public static function getUsageCounts(int $contextId): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('review_assignments AS ra')
            ->join('submissions AS s', 's.submission_id', '=', 'ra.submission_id')
            ->where('s.context_id', $contextId)
            ->whereNotNull('ra.recommendation')
            ->where('ra.recommendation', '!=', 0)
            ->groupBy('ra.recommendation')
            ->select('ra.recommendation', \Illuminate\Support\Facades\DB::raw('COUNT(*) AS cnt'))
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->recommendation] = (int) $row->cnt;
        }
        return $counts;
    }

    /**
     * @copydoc Plugin::getContextSpecificPluginSettingsFile()
     */
    public function getContextSpecificPluginSettingsFile(): string
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb): array
    {
        $actions = parent::getActions($request, $verb);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        $url = $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']);
        array_unshift($actions, new LinkAction('settings', new AjaxModal($url, $this->getDisplayName()), __('manager.plugins.settings')));
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings') {
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
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.reviewerRecommendationManager.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription(): string
    {
        return __('plugins.generic.reviewerRecommendationManager.description');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\reviewerRecommendationManager\ReviewerRecommendationManagerPlugin', '\ReviewerRecommendationManagerPlugin');
}
