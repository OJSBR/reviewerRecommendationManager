{**
 * templates/settingsForm.tpl
 *
 * Plugin autoral OJSBR.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Configuração das recomendações do avaliador (renomear / reordenar / desativar).
 *}
<script>
	$(function() {ldelim}
		$('#reviewerRecommendationManagerSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

		var $list = $('#rrmSortable');

		function rrmRenumber() {ldelim}
			$list.children('.rrmCard').each(function(i) {ldelim}
				$(this).find('.rrmOrder').val(i + 1);
				$(this).find('.rrmPos').text(i + 1);
			{rdelim});
		{rdelim}

		if ($.fn.sortable) {ldelim}
			$list.sortable({ldelim}
				handle: '.rrmHandle',
				axis: 'y',
				containment: 'parent',
				tolerance: 'pointer',
				placeholder: 'rrmPlaceholder',
				forcePlaceholderSize: true,
				update: rrmRenumber
			{rdelim});
		{rdelim}

		// Estado visual "desativada" no card inteiro, pra não restar dúvida do que está ligado.
		$list.on('change', '.rrmToggle input[type="checkbox"]', function() {ldelim}
			$(this).closest('.rrmCard').toggleClass('rrmCard--off', !this.checked);
		{rdelim});
		$list.find('.rrmToggle input[type="checkbox"]').each(function() {ldelim}
			$(this).closest('.rrmCard').toggleClass('rrmCard--off', !this.checked);
		{rdelim});

		rrmRenumber();
	{rdelim});
</script>

<style>
	/* ---- lista ---- */
	#rrmSortable {ldelim} list-style:none; margin:1em 0 0; padding:0; {rdelim}

	/* ---- card ---- */
	.rrmCard {ldelim}
		list-style:none; background:#fff; border:1px solid #d8dee4; border-radius:8px;
		margin:0 0 1.15em; overflow:hidden; box-shadow:0 1px 2px rgba(27,31,35,.05);
		transition:border-color .15s, opacity .15s;
	{rdelim}
	.rrmCard:hover {ldelim} border-color:#b6c2cd; {rdelim}
	.rrmCard.ui-sortable-helper {ldelim} box-shadow:0 8px 24px rgba(27,31,35,.18); border-color:#3a6ea5; {rdelim}
	.rrmPlaceholder {ldelim} border:2px dashed #b6c2cd; border-radius:8px; background:#f2f5f8; margin:0 0 1.15em; {rdelim}
	.rrmCard--off {ldelim} opacity:.72; background:#fbfbfc; {rdelim}
	.rrmCard--off .rrmCardHead {ldelim} background:#f2f3f5; {rdelim}

	/* ---- cabeçalho do card: identidade do item ---- */
	.rrmCardHead {ldelim}
		display:flex; align-items:center; gap:.85em;
		padding:.85em 1.1em; background:#f4f7fb; border-bottom:1px solid #e3e9ef;
	{rdelim}
	.rrmHandle {ldelim}
		cursor:grab; color:#8a97a4; font-size:1.25em; line-height:1;
		user-select:none; padding:.15em .1em; flex:0 0 auto;
	{rdelim}
	.rrmHandle:active {ldelim} cursor:grabbing; {rdelim}
	.rrmHandle:hover {ldelim} color:#3a6ea5; {rdelim}
	.rrmPos {ldelim}
		flex:0 0 auto; min-width:1.9em; height:1.9em; line-height:1.9em; text-align:center;
		background:#3a6ea5; color:#fff; border-radius:50%; font-size:.8em; font-weight:700;
	{rdelim}
	.rrmIdent {ldelim} flex:1 1 auto; min-width:0; {rdelim}
	.rrmIdentTag {ldelim}
		display:block; font-size:.68em; text-transform:uppercase; letter-spacing:.06em;
		color:#61707e; font-weight:700; margin-bottom:.15em;
	{rdelim}
	.rrmIdentText {ldelim} display:block; font-size:1.12em; font-weight:700; color:#16232f; line-height:1.25; {rdelim}

	/* ---- selo de impacto ---- */
	.rrmBadge {ldelim}
		flex:0 0 auto; font-size:.8em; line-height:1.3; padding:.35em .75em; border-radius:999px;
		max-width:16em; text-align:right;
	{rdelim}
	.rrmBadge--warn {ldelim} background:#fdecea; color:#96231f; border:1px solid #f2c4bf; {rdelim}
	.rrmBadge--ok {ldelim} background:#eef5ef; color:#4a6b4d; border:1px solid #cfe3d1; {rdelim}

	/* ---- corpo do card ---- */
	.rrmCardBody {ldelim} padding:1.1em; {rdelim}
	.rrmField {ldelim} margin:0 0 1em; {rdelim}
	.rrmField > label, .rrmFieldLabel {ldelim}
		display:block; font-weight:600; color:#33414e; margin-bottom:.35em; font-size:.92em;
	{rdelim}
	/* o campo multilíngue ocupa a largura toda, sem apertar */
	.rrmField input[type="text"] {ldelim} width:100%; box-sizing:border-box; {rdelim}

	/* ---- faixa exclusiva do checkbox: sem ambiguidade de dono ---- */
	.rrmToggle {ldelim}
		display:flex; align-items:center; gap:.65em;
		padding:.7em .9em; background:#f6f8fa; border:1px solid #e1e6eb; border-radius:6px;
	{rdelim}
	.rrmToggle ul, .rrmToggle ol, .rrmToggle li {ldelim}
		list-style:none !important; margin:0 !important; padding:0 !important; display:inline;
	{rdelim}
	.rrmToggle input[type="checkbox"] {ldelim} margin:0 .2em 0 0; {rdelim}
	.rrmToggle label {ldelim} margin:0; font-weight:600; color:#33414e; cursor:pointer; {rdelim}
	.rrmCard--off .rrmToggle {ldelim} background:#f0f1f3; border-color:#dcdfe3; {rdelim}

	/* ---- avisos do topo ---- */
	.rrmNotice {ldelim}
		margin:1em 0; padding:.9em 1.1em; border:1px solid #e6cf6a; border-left:4px solid #d8b520;
		background:#fffbe9; border-radius:6px; line-height:1.5;
	{rdelim}
	.rrmNotice strong {ldelim} color:#6b5600; {rdelim}
	.rrmHint {ldelim} color:#61707e; margin:.6em 0 0; font-size:.93em; {rdelim}
</style>

<form
	class="pkp_form"
	id="reviewerRecommendationManagerSettingsForm"
	method="post"
	action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="reviewerRecommendationManagerSettingsFormNotification"}

	<div id="description">{translate key="plugins.generic.reviewerRecommendationManager.settings.description"}</div>

	<div class="rrmNotice">
		<strong>{translate key="plugins.generic.reviewerRecommendationManager.settings.historyNotice.title"}</strong><br />
		{translate key="plugins.generic.reviewerRecommendationManager.settings.historyNotice.body"}
	</div>

	<p class="rrmHint">{translate key="plugins.generic.reviewerRecommendationManager.settings.dragHint"}</p>

	{fbvFormArea id="reviewerRecommendationManagerArea"}
		<ol id="rrmSortable">
			{foreach from=$recommendations item=rec}
				<li class="rrmCard" data-code="{$rec.code}">

					<div class="rrmCardHead">
						<span class="rrmHandle" title="{translate key="plugins.generic.reviewerRecommendationManager.settings.dragHint"}">&#9776;</span>
						<span class="rrmPos">{$rec.order|escape}</span>
						<input type="hidden" name="order_{$rec.code}" value="{$rec.order|escape}" class="rrmOrder" />

						<span class="rrmIdent">
							<span class="rrmIdentTag">{translate key="plugins.generic.reviewerRecommendationManager.settings.originalLabel"}</span>
							<span class="rrmIdentText">{$rec.original|escape}</span>
						</span>

						{if $rec.usage > 0}
							<span class="rrmBadge rrmBadge--warn">{translate key="plugins.generic.reviewerRecommendationManager.settings.usageWarning" usage=$rec.usage}</span>
						{else}
							<span class="rrmBadge rrmBadge--ok">{translate key="plugins.generic.reviewerRecommendationManager.settings.usageSafe"}</span>
						{/if}
					</div>

					<div class="rrmCardBody">
						<div class="rrmField">
							{fbvElement
								type="text"
								multilingual=true
								name="label_`$rec.code`"
								id="label_`$rec.code`"
								value=$rec.label
								label="plugins.generic.reviewerRecommendationManager.settings.customLabel"
								size=$fbvStyles.size.LARGE
							}
						</div>

						<div class="rrmToggle">
							{fbvElement
								type="checkbox"
								name="enabled_`$rec.code`"
								id="enabled_`$rec.code`"
								value="1"
								checked=$rec.enabled
								label="plugins.generic.reviewerRecommendationManager.settings.enabled"
							}
						</div>
					</div>

				</li>
			{/foreach}
		</ol>
	{/fbvFormArea}

	{fbvFormButtons}

	<p class="rrmHint">{translate key="plugins.generic.reviewerRecommendationManager.settings.disableNote"}</p>
</form>
