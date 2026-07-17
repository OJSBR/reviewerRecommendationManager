<?php

/**
 * @file ReviewerRecommendationSettingsForm.php
 *
 * Plugin autoral OJSBR.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRecommendationSettingsForm
 *
 * @brief Formulário multilíngue para renomear, reordenar e desativar as
 *        recomendações do avaliador.
 */

namespace APP\plugins\generic\reviewerRecommendationManager;

use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class ReviewerRecommendationSettingsForm extends Form
{
    public function __construct(private ReviewerRecommendationManagerPlugin $plugin, private int $contextId)
    {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Códigos das recomendações (1..6) na ordem nativa.
     *
     * @return array<int, string>
     */
    private function codes(): array
    {
        return array_map('intval', array_keys(ReviewerRecommendationManagerPlugin::getRecommendationKeyMap()));
    }

    /**
     * Rótulos (label_1..label_6) são campos localizados.
     */
    public function getLocaleFieldNames(): array
    {
        return array_map(fn ($code) => "label_{$code}", $this->codes());
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData(): void
    {
        foreach ($this->codes() as $code) {
            $this->setData("label_{$code}", $this->plugin->getCustomLabels($this->contextId, $code));
            $this->setData("enabled_{$code}", $this->plugin->isRecommendationEnabled($this->contextId, $code));
            $this->setData("order_{$code}", $this->plugin->getRecommendationOrder($this->contextId, $code));
        }
        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData(): void
    {
        $vars = [];
        foreach ($this->codes() as $code) {
            $vars[] = "label_{$code}";
            $vars[] = "enabled_{$code}";
            $vars[] = "order_{$code}";
        }
        $this->readUserVars($vars);
        parent::readInputData();
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false): string
    {
        $templateMgr = TemplateManager::getManager($request);

        $keyMap = ReviewerRecommendationManagerPlugin::getRecommendationKeyMap();
        $usage = ReviewerRecommendationManagerPlugin::getUsageCounts($this->contextId);

        $recommendations = [];
        foreach ($keyMap as $code => $localeKey) {
            $code = (int) $code;
            $order = $this->getData("order_{$code}");
            $recommendations[] = [
                'code' => $code,
                'nativeKey' => $localeKey,
                'original' => $this->plugin->getOriginalLabel($localeKey),
                'usage' => $usage[$code] ?? 0,
                'label' => $this->getData("label_{$code}") ?: [],
                'enabled' => (bool) $this->getData("enabled_{$code}"),
                'order' => ($order === null || $order === '') ? $code : (int) $order,
            ];
        }

        // Renderiza na ordem salva para o arrastar-e-soltar refletir o estado atual.
        usort($recommendations, fn ($a, $b) => $a['order'] <=> $b['order']);

        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'recommendations' => $recommendations,
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        foreach ($this->codes() as $code) {
            $labels = $this->getData("label_{$code}");
            $labels = is_array($labels) ? array_map(fn ($v) => trim((string) $v), $labels) : [];
            $labels = array_filter($labels, fn ($v) => $v !== '');

            $this->plugin->updateSetting($this->contextId, "label_{$code}", $labels, 'object');
            $this->plugin->updateSetting($this->contextId, "enabled_{$code}", (bool) $this->getData("enabled_{$code}"), 'bool');

            $order = $this->getData("order_{$code}");
            $order = ($order === null || $order === '') ? $code : (int) $order;
            $this->plugin->updateSetting($this->contextId, "order_{$code}", $order, 'int');
        }

        // Regera os .po de sobrescrita a partir dos rótulos salvos.
        $this->plugin->regenerateOverrideFiles($this->contextId);

        parent::execute(...$functionArgs);
    }
}
