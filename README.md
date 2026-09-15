# Reviewer Recommendation Manager — OJS plugin

[![OJS](https://img.shields.io/badge/OJS-3.4%20%7C%203.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-1.0.4.1-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OJS 3.5](https://github.com/OJSBR/reviewerRecommendationManager/releases/download/1.0.4.1/reviewerRecommendationManager-1.0.4.1.tar.gz) · [OJS 3.4](https://github.com/OJSBR/reviewerRecommendationManager/releases/download/1.0.4.1-ojs3.4/reviewerRecommendationManager-1.0.4.1-ojs3.4.tar.gz) — or browse all [Releases](../../releases).

> **This is the `stable-3_4_0` branch (OJS 3.4).** For OJS 3.5 use the
> [`stable-3_5_0`](../../tree/stable-3_5_0) branch.

A generic plugin for **Open Journal Systems (OJS)** that lets a journal **rename
(multilingual), reorder and disable** the recommendations a reviewer picks when completing a
review (Accept, Revisions Required, Resubmit, Decline, See Comments, …) — **without patching
OJS core** and **preserving the historical record** of reviews already submitted.

> **Developed and maintained by [OJSBR](https://ojsbr.com).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OJS version | Branch | Plugin release |
|-------------|--------|----------------|
| OJS 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.4.1 |
| OJS 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.4.1 |

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

- **PHPUnit** (`tests/*Test.php`, on PKP's `PKPTestCase`): the plugin classes against the installed
  PKP, stored labels without markup or Vue delimiters, the reviewer form filtered (disabled options
  dropped, configured order, only on the reviewer step), no settings action at site level, the
  usage count, a settings template that only initialises the form, and the translations. From the
  OJS root:

  ```bash
  lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage "$PWD/plugins/generic/reviewerRecommendationManager/tests"
  ```

- **Cypress** (`cypress/tests/functional/ReviewerRecommendationManager.cy.js`, run by
  [pkp-github-actions](https://github.com/pkp/pkp-github-actions) on every push): enables the
  plugin, lists the six recommendations with the core wording, saves a renamed and a disabled one
  (markup and Vue delimiters removed) and puts the settings back. With `reviewerUser`,
  `reviewerPassword` and `reviewSubmissionId` it also checks the list a reviewer receives.
- Verified on OJS 3.5.0.3 and 3.4.0.10.

Tests are kept in the repository and are not part of the release package.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com) — original plugin.
- Distributed under the **GNU GPL v3**.

## AI use

Generative AI (Claude, by Anthropic) was used to write and run tests, improve the code and bring
it in line with PKP standards. Every change is reviewed and tested by OJSBR, which is responsible
for the published releases.

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

> **Esta é a branch `stable-3_4_0` (OJS 3.4).** Para o OJS 3.5 use a branch
> [`stable-3_5_0`](../../tree/stable-3_5_0).

### Compatibilidade e branches

| Versão do OJS | Branch | Release do plugin |
|---------------|--------|-------------------|
| OJS 3.5.x     | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.0.4.1 |
| OJS 3.4.x     | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.4.1 |

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

PHPUnit em `tests/*Test.php`, sobre o `PKPTestCase` do PKP: classes do plugin contra o PKP instalado,
rótulos gravados sem marcação nem delimitadores do Vue, formulário do avaliador filtrado (opções
desativadas fora, ordem configurada, só no passo do avaliador), nenhuma ação de configuração no
nível do site, contagem de uso, template de configuração que só inicializa o formulário e as
traduções. Cypress em `cypress/tests/functional/`, rodado pelo
[pkp-github-actions](https://github.com/pkp/pkp-github-actions) a cada push: liga o plugin, lista as
seis recomendações com o texto do núcleo, salva uma renomeada e uma desativada (sem marcação) e
devolve a configuração; com `reviewerUser`, `reviewerPassword` e `reviewSubmissionId` confere também
a lista que o avaliador recebe. Verificado no OJS 3.5.0.3 e 3.4.0.10. Os testes ficam no repositório
e não vão no pacote de release.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com) — plugin autoral.
- Distribuído sob a **GNU GPL v3**.

### Uso de IA

Foi usada IA generativa (Claude, da Anthropic) para escrever e rodar testes, melhorar o código e
alinhá-lo aos padrões da PKP. Toda mudança é revisada e testada pela OJSBR, que responde pelas
releases publicadas.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
