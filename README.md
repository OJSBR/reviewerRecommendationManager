# Reviewer Recommendation Manager — OJS plugin

[![OJS](https://img.shields.io/badge/OJS-3.4%20%7C%203.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-1.0.3.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OJS 3.5](https://github.com/OJSBR/reviewerRecommendationManager/releases/download/1.0.3.1/reviewerRecommendationManager-1.0.3.1.tar.gz) · [OJS 3.4](https://github.com/OJSBR/reviewerRecommendationManager/releases/download/1.0.3.1-ojs3.4/reviewerRecommendationManager-1.0.3.1-ojs3.4.tar.gz) — or browse all [Releases](../../releases).

A generic plugin for **Open Journal Systems (OJS)** that lets a journal **rename
(multilingual), reorder and disable** the recommendations a reviewer picks when completing a
review (Accept, Revisions Required, Resubmit, Decline, See Comments, …) — **without patching
OJS core** and **preserving the historical record** of reviews already submitted.

> **Developed and maintained by [OJSBR](https://ojsbr.com).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OJS version | Branch | Plugin release |
|-------------|--------|----------------|
| OJS 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.3.0 |
| OJS 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.3.0-ojs3.4 |

> **OJS 3.6 note.** PKP has implemented customizable reviewer recommendations in the core
> for OJS 3.6 ([pkp/pkp-lib#1660](https://github.com/pkp/pkp-lib/issues/1660)). This plugin
> targets **OJS 3.4 and 3.5**, where the six recommendations are still hard-coded.

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
- **Original reference** — rebuilds the locale bundle *excluding* the plugin's override path,
  so the settings screen always shows OJS's original text even after a rename.
- Works over the **six native recommendation codes** (1–6); it does not add new codes, which
  keeps it fully core- and upgrade-compatible. Disabling the plugin restores default behavior.

## Tests

A functional [Cypress](https://www.cypress.io/) test lives in
`cypress/tests/functional/ReviewerRecommendationManager.cy.js`. It enables the plugin, opens
its settings, renames one recommendation, disables another, saves and reopens the form to
assert both changes were persisted, following the conventions of the tests shipped with OJS
plugins.

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
| OJS 3.5.x     | `stable-3_5_0` *(padrão)* | 1.0.3.0 |
| OJS 3.4.x     | `stable-3_4_0` | 1.0.3.0-ojs3.4 |

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
em `plugins/generic/` (ficando `plugins/generic/reviewerRecommendationManager/`). Depois ative
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

Um teste funcional [Cypress](https://www.cypress.io/) fica em
`cypress/tests/functional/ReviewerRecommendationManager.cy.js`. Ele habilita o plugin, abre as
configurações, renomeia uma recomendação, desativa outra, salva e reabre o formulário para
conferir que as duas mudanças persistiram.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com) — plugin autoral.
- Distribuído sob a **GNU GPL v3**.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
