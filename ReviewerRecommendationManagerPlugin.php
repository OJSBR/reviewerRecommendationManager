<?php

/**
 * @file ReviewerRecommendationManagerPlugin.php
 *
 * Plugin autoral OJSBR.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRecommendationManagerPlugin
 *
 * @brief Permite renomear (multilíngue), reordenar e desativar as recomendações
 *        do avaliador, sem alterar o núcleo do OJS e preservando o histórico.
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
    /** Pasta (dentro do files_dir do contexto) onde os .po de sobrescrita são gravados. */
    public const OVERRIDE_FOLDER = 'reviewerRecommendations';

    /** Template do passo 3 do avaliador — onde a lista de recomendações é montada. */
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

        // 1) Renomear: registra os .po de sobrescrita (por contexto) com prioridade máxima.
        $this->registerLabelOverrides();

        // 2) Reordenar / desativar: ajusta a lista SÓ no formulário do avaliador.
        Hook::add('TemplateManager::fetch', [$this, 'filterReviewerForm']);

        return true;
    }

    /**
     * Registra a pasta de sobrescrita de traduções do contexto atual, se existir.
     * A pasta é per-contexto (ContextFileManager), então cada revista vê só os seus rótulos.
     */
    public function registerLabelOverrides(): void
    {
        $path = $this->getOverrideStoragePath(false);
        if ($path && is_dir($path)) {
            Locale::registerPath($path, PHP_INT_MAX);
        }
    }

    /**
     * Hook TemplateManager::fetch — reordena e remove as opções desativadas
     * na variável passada ao template do passo 3 do avaliador. Não toca no HTML,
     * apenas na variável antes do render, e não afeta a visão do editor.
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

        // Preserva a entrada "Escolha uma opção" ('') sempre no topo.
        $chooseOne = [];
        if (array_key_exists('', $options)) {
            $chooseOne[''] = $options[''];
            unset($options['']);
        }

        // Remove desativadas e ordena pelo campo de ordem configurado.
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
     * Mapa código => chave de locale nativa das 6 recomendações (sem a entrada '').
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
     * Rótulo ORIGINAL do OJS para uma chave, ignorando a sobrescrita do plugin.
     * Serve de âncora de referência na tela de configuração (nunca renomeado).
     */
    public function getOriginalLabel(string $key, ?string $locale = null): string
    {
        $locale ??= Locale::getLocale();
        $bundle = Locale::getBundle($locale, false);

        $storagePath = $this->getOverrideStoragePath(false);
        $storagePath = $storagePath ? realpath($storagePath) : false;
        if ($storagePath) {
            // Remove as entradas de sobrescrita do plugin para recuperar a tradução nativa.
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
     * Rótulos customizados de um código (array locale => texto), ou [] se não configurado.
     */
    public function getCustomLabels(int $contextId, int $code): array
    {
        $value = $this->getSetting($contextId, "label_{$code}");
        return is_array($value) ? $value : [];
    }

    public function isRecommendationEnabled(int $contextId, int $code): bool
    {
        $value = $this->getSetting($contextId, "enabled_{$code}");
        // Ausência de configuração => habilitada por padrão (comportamento nativo).
        return $value === null ? true : (bool) $value;
    }

    public function getRecommendationOrder(int $contextId, int $code): int
    {
        $value = $this->getSetting($contextId, "order_{$code}");
        return $value === null || $value === '' ? $code : (int) $value;
    }

    /**
     * Caminho da pasta de sobrescrita de traduções do contexto atual.
     *
     * @param bool $create Cria a pasta base do contexto se não existir.
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
     * Regenera os arquivos .po de sobrescrita a partir dos rótulos configurados.
     * Só grava a chave quando há rótulo customizado não-vazio para aquele idioma;
     * caso contrário mantém a tradução nativa do OJS.
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

        // Invalida caches de bundle para refletir os novos rótulos já nesta requisição.
        Locale::registerPath($basePath, PHP_INT_MAX);
    }

    /**
     * Contagem de pareceres históricos por recomendação, no contexto (para o aviso de impacto).
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
