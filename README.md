# Reviewer Recommendation Manager — OJS plugin

[![OJS](https://img.shields.io/badge/OJS-3.4%20%7C%203.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-1.0.4.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OJS 3.5](https://github.com/OJSBR/reviewerRecommendationManager/releases/download/1.0.4.0/reviewerRecommendationManager-1.0.4.0.tar.gz) · [OJS 3.4](https://github.com/OJSBR/reviewerRecommendationManager/releases/download/1.0.4.0-ojs3.4/reviewerRecommendationManager-1.0.4.0-ojs3.4.tar.gz) — or browse all [Releases](../../releases).

A generic plugin for **Open Journal Systems (OJS)** that lets a journal **rename
(multilingual), reorder and disable** the recommendations a reviewer picks when completing a
review (Accept, Revisions Required, Resubmit, Decline, See Comments, …) — **without patching
OJS core** and **preserving the historical record** of reviews already submitted.

> **Developed and maintained by [OJSBR](https://ojsbr.com).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OJS version | Branch | Plugin release |
|-------------|--------|----------------|
| OJS 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.4.0 |
| OJS 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.4.0 |

> **OJS 3.6 note.** PKP has implemented customizable reviewer recommendations in the core
> for OJS 3.6 ([pkp/pkp-lib#1660](https://github.com/pkp/pkp-lib/issues/1660)). This plugin
> targets **OJS 3.4 and 3.5**, where the six recommendations are still hard-coded.

Both branches ship the same code. The locale folders follow the codes of each OJS line
(OJS 3.4: `fr_FR`, `pt_PT`, `nb`, `sr@latin`, `zh_CN`; OJS 3.5: `fr`, `pt`, `nb_NO`, `sr_Latn`,
`zh_Hans`).

## The problem

OJS 3.4 and 3.5 offer reviewers six fixed recommendations with fixed wording. Journals whose
policy uses other terms — "Accept with minor revisions", "Not suitable for this journal" — or
that never want reviewers to pick one of them have no setting for it: the only way is to edit
the core translation files, which every upgrade overwrites and which apply to every journal of
the installation.

## What it does

- **Rename** the six built-in reviewer recommendations, per language. Because every OJS
  surface resolves the label through `__()`, the new text shows up **everywhere** — the
  reviewer form, the editor's dashboard, the author's view, e-mails and the REST API.
- **Reorder** the options — affecting the **reviewer form only** (drag-and-drop).
- **Disable** an option — removing it from the **reviewer form only**. Editors keep seeing
  that recommendation on past reviews that used it; the record is never hidden.
- **Impact awareness** — the settings screen shows, per option, how many historical reviews
  already use it, so you know when a rename is retroactive.

## Installation

1. Download the release for your OJS version (or clone the matching branch).
2. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the folder
   into `plugins/generic/` so you get `plugins/generic/reviewerRecommendationManager/`.
   Do not rename the folder: OJS derives the plugin's class namespace from the directory name.
3. Enable **Reviewer Recommendation Manager** under the *Generic* plugins list.

## Configuration

Open the plugin settings. For each of the six native recommendations you can set a
**multilingual label**, **drag to reorder**, and toggle **availability in the reviewer form**.
The original OJS text is always shown as a fixed reference anchor, and an impact badge warns
when renaming affects existing reviews.

> **Governance note.** Renaming is *retroactive*: reviews already submitted display with the
> new text. If a change alters the **meaning** of the answer, prefer disabling the old option
> and repurposing an option with **0 reviews** for the new meaning — this avoids falsifying
> the record.

## How it works (technical)

- **Rename** — generates per-context gettext `.po` override files (via `ContextFileManager`)
  and registers them with `Locale::registerPath($path, PHP_INT_MAX)`, so the six native
  locale keys resolve to the configured labels everywhere.
- **Reorder / disable** — a `TemplateManager::fetch` hook adjusts the
  `reviewerRecommendationOptions` variable on `reviewer/review/step3.tpl` before render — the
  editor's view is untouched.
- **Labels are plain text.** They become translations of core keys that the core prints
  without escaping (the reviewer grid builds its cell HTML with them) and that also reach
  pages mounted by Vue, so markup is removed on save and the Vue delimiters `{{ }}` are broken
  apart (`{ { } }`).
- **Original reference** — rebuilds the locale bundle *excluding* the plugin's override path,
  so the settings screen always shows OJS's original text even after a rename.
- Works over the **six native recommendation codes** (1–6); it does not add new codes, which
  keeps it fully core- and upgrade-compatible. Disabling the plugin restores default behavior.

## Tests

- **PHP suite** (`tests/`, 19 tests): the plugin classes against the installed PKP (return
  types of the overridden methods), the six core recommendations, label sanitization (markup,
  entities, Vue delimiters), the settings template (CSRF, escaping, no hard-coded text) and the
  38 translations (identical keys, placeholders, fuzzy markers). Run either way from the OJS
  root:

  ```bash
  php plugins/generic/reviewerRecommendationManager/tests/run.php
  lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage "$PWD/plugins/generic/reviewerRecommendationManager/tests"
  ```

- **Cypress** (`cypress/tests/functional/ReviewerRecommendationManager.cy.js`): the settings
  (six recommendations, a renamed label saved without markup, a disabled option) and the list
  a reviewer actually receives at step 3. Every setting touched is restored at the end.
  Captcha on login must be off for the run.

  ```bash
  npx cypress run --config specPattern='plugins/generic/reviewerRecommendationManager/cypress/tests/functional/*.cy.js' \
    --env contextPath=<journal>,formLocale=<locale>,adminUser=<user>,adminPassword=<password>,reviewerUser=<user>,reviewerPassword=<password>,reviewSubmissionId=<id>
  ```

- Verified on OJS 3.5.0.3 and 3.4.0.10: a renamed label resolved by the core translation, the
  reviewer list reordered with the disabled option gone, other templates untouched, and the
  Cypress spec green on both.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com) — original plugin.
- Distributed under the **GNU GPL v3**.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OJS version you
are working against. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

Plugin genérico para o **Open Journal Systems (OJS)** que permite à revista **renomear
(multilíngue), reordenar e desativar** as recomendações que o avaliador escolhe ao concluir a
avaliação (Aceitar, Correções obrigatórias, Submeter novamente, Rejeitar, Ver comentários…) —
**sem alterar o núcleo do OJS** e **preservando o histórico** dos pareceres já emitidos.

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com).** Veja a seção
> [Créditos e autoria](#créditos-e-autoria) abaixo.

### Compatibilidade e branches

| Versão do OJS | Branch | Release do plugin |
|---------------|--------|-------------------|
| OJS 3.5.x     | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.0.4.0 |
| OJS 3.4.x     | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.4.0 |

As duas branches têm o mesmo código. As pastas de idioma seguem os códigos de cada linha do OJS
(OJS 3.4: `fr_FR`, `pt_PT`, `nb`, `sr@latin`, `zh_CN`; OJS 3.5: `fr`, `pt`, `nb_NO`, `sr_Latn`,
`zh_Hans`).

### O problema

O OJS 3.4 e 3.5 oferecem ao avaliador seis recomendações fixas, com texto fixo. Revistas cuja
política usa outros termos — "Aceitar com pequenas correções", "Fora do escopo da revista" — ou
que não querem que o avaliador escolha uma delas não têm onde configurar isso: o único caminho é
editar os arquivos de tradução do núcleo, que cada atualização sobrescreve e que valem para todas
as revistas da instalação.

### O que faz

- **Renomear** as seis recomendações nativas, por idioma. Como todo o OJS resolve o rótulo
  via `__()`, o novo texto aparece **em todo lugar** — formulário do avaliador, painel do
  editor, visão do autor, e-mails e API REST.
- **Reordenar** as opções — afetando **apenas o formulário do avaliador** (arrastar-e-soltar).
- **Desativar** uma opção — removendo-a **apenas do formulário do avaliador**. O editor
  continua vendo essa recomendação nos pareceres antigos que a utilizaram; o histórico nunca
  é escondido.
- **Consciência de impacto** — a tela de configuração mostra, por opção, quantos pareceres
  históricos já a usam, deixando claro quando um renomear é retroativo.

### Instalação

Instale em **Configurações → Website → Plugins → Enviar um novo plugin**, ou extraia a pasta
em `plugins/generic/` (ficando `plugins/generic/reviewerRecommendationManager/`). Não renomeie a
pasta: o OJS deriva o namespace da classe do nome do diretório. Depois ative
o **Reviewer Recommendation Manager** na lista de plugins *Genéricos*.

### Configuração

Nas configurações, para cada uma das seis recomendações nativas defina um **rótulo
multilíngue**, **arraste para reordenar** e ligue/desligue a **disponibilidade no formulário
do avaliador**. O texto original do OJS é sempre exibido como âncora de referência, e um
selo de impacto avisa quando o renomear afeta pareceres existentes.

> **Nota de governança.** Renomear é *retroativo*: pareceres já emitidos passam a exibir o
> novo texto. Se a mudança altera o **sentido** da resposta, prefira desativar a opção antiga
> e reaproveitar uma opção com **0 pareceres** para o novo significado — assim o histórico
> não é falsificado.

### Testes

Suíte PHP em `tests/` (19 testes, pelo `tests/run.php` ou pelo PHPUnit do PKP) e Cypress em
`cypress/tests/functional/`, com os comandos da seção em inglês. A suíte cobre as classes do
plugin contra o PKP instalado, as seis recomendações do núcleo, a limpeza dos rótulos (marcação,
entidades, delimitadores do Vue), o template e as 38 traduções; o Cypress cobre as configurações
e a lista que o avaliador recebe no passo 3, restaurando tudo no fim.

Os rótulos são texto puro: viram traduções de chaves do núcleo que o núcleo imprime sem escapar,
então a marcação é removida ao salvar e os delimitadores `{{ }}` do Vue são separados.

Verificado no OJS 3.5.0.3 e 3.4.0.10: rótulo renomeado resolvido pela tradução do núcleo, lista
do avaliador reordenada e sem a opção desativada, demais templates intactos e o Cypress verde nas
duas versões.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com) — plugin autoral.
- Distribuído sob a **GNU GPL v3**.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
