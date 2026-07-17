{**
 * templates/settingsForm.tpl
 *
 * Plugin autoral OJSBR.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Configuração das recomendações do avaliador (renomear / reordenar / desativar).
 *
 * NOTA: o checkbox é escrito à mão de propósito. O {fbvElement type="checkbox"} da PKP
 * renderiza um <li> cru (lib/pkp/templates/form/checkbox.tpl) e só é válido dentro de
 * {fbvFormSection list=true}, que o embrulha num <ul>. Dentro dos cards, esse <li> era
 * expulso pelo parser de HTML e acabava colado no item seguinte.
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
				items: '> .rrmCard',
				axis: 'y',
				containment: 'parent',
				tolerance: 'pointer',
				placeholder: 'rrmPlaceholder',
				forcePlaceholderSize: true,
				update: rrmRenumber
			{rdelim});
		{rdelim}

		function rrmSync($cb) {ldelim}
			$cb.closest('.rrmCard').toggleClass('rrmCard--off', !$cb.prop('checked'));
		{rdelim}
		$list.on('change', '.rrmToggleInput', function() {ldelim} rrmSync($(this)); {rdelim});
		$list.find('.rrmToggleInput').each(function() {ldelim} rrmSync($(this)); {rdelim});

		rrmRenumber();
	{rdelim});
</script>

<style>
	#rrmSortable {ldelim} margin:1em 0 0; padding:0; {rdelim}

	.rrmCard {ldelim}
		background:#fff; border:1px solid #d8dee4; border-radius:8px;
		margin:0 0 1.15em; overflow:hidden; box-shadow:0 1px 2px rgba(27,31,35,.05);
	{rdelim}
	.rrmCard:hover {ldelim} border-color:#b6c2cd; {rdelim}
	.rrmCard.ui-sortable-helper {ldelim} box-shadow:0 8px 24px rgba(27,31,35,.18); border-color:#3a6ea5; {rdelim}
	.rrmPlaceholder {ldelim} border:2px dashed #b6c2cd; border-radius:8px; background:#f2f5f8; margin:0 0 1.15em; {rdelim}
	.rrmCard--off {ldelim} opacity:.72; background:#fbfbfc; {rdelim}
	.rrmCard--off .rrmCardHead {ldelim} background:#f2f3f5; {rdelim}

	.rrmCardHead {ldelim}
		display:flex; align-items:center; gap:.85em;
		padding:.85em 1.1em; background:#f4f7fb; border-bottom:1px solid #e3e9ef;
	{rdelim}
	.rrmHandle {ldelim} cursor:grab; color:#8a97a4; font-size:1.25em; line-height:1; user-select:none; flex:0 0 auto; {rdelim}
	.rrmHandle:active {ldelim} cursor:grabbing; {rdelim}
	.rrmHandle:hover {ldelim} color:#3a6ea5; {rdelim}
	.rrmPos {ldelim}
		flex:0 0 auto; min-width:1.9em; height:1.9em; line-height:1.9em; text-align:center;
		background:#3a6ea5; color:#fff; border-radius:50%; font-size:.8em; font-weight:700;
	{rdelim}
	.rrmIdent {ldelim} flex:1 1 auto; min-width:0; {rdelim}
	.rrmIdentTag {ldelim} display:block; font-size:.68em; text-transform:uppercase; letter-spacing:.06em; color:#61707e; font-weight:700; margin-bottom:.15em; {rdelim}
	.rrmIdentText {ldelim} display:block; font-size:1.12em; font-weight:700; color:#16232f; line-height:1.25; {rdelim}

	.rrmBadge {ldelim} flex:0 0 auto; font-size:.8em; line-height:1.3; padding:.35em .75em; border-radius:999px; max-width:16em; text-align:right; {rdelim}
	.rrmBadge--warn {ldelim} background:#fdecea; color:#96231f; border:1px solid #f2c4bf; {rdelim}
	.rrmBadge--ok {ldelim} background:#eef5ef; color:#4a6b4d; border:1px solid #cfe3d1; {rdelim}

	.rrmCardBody {ldelim} padding:1.1em; {rdelim}
	.rrmField {ldelim} margin:0 0 1em; {rdelim}
	.rrmField input[type="text"] {ldelim} width:100%; box-sizing:border-box; {rdelim}

	/* faixa do checkbox, escrita à mão — fica DENTRO do card, sem <li> */
	.rrmToggle {ldelim}
		display:flex; align-items:center; gap:.65em;
		padding:.75em .9em; background:#f6f8fa; border:1px solid #e1e6eb; border-radius:6px;
		cursor:pointer;
	{rdelim}
	.rrmToggle:hover {ldelim} background:#eef2f6; border-color:#cfd7df; {rdelim}
	.rrmToggleInput {ldelim} margin:0; flex:0 0 auto; width:16px; height:16px; cursor:pointer; {rdelim}
	.rrmToggleText {ldelim} margin:0; font-weight:600; color:#33414e; cursor:pointer; line-height:1.3; {rdelim}
	.rrmCard--off .rrmToggle {ldelim} background:#f0f1f3; border-color:#dcdfe3; {rdelim}
	.rrmCard--off .rrmToggleText {ldelim} color:#6b7684; {rdelim}

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
		<div id="rrmSortable">
			{foreach from=$recommendations item=rec}
				<div class="rrmCard" data-code="{$rec.code}">

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

						<label class="rrmToggle" for="enabled_{$rec.code}">
							<input
								type="checkbox"
								class="rrmToggleInput"
								id="enabled_{$rec.code}"
								name="enabled_{$rec.code}"
								value="1"
								{if $rec.enabled} checked="checked"{/if}
							/>
							<span class="rrmToggleText">{translate key="plugins.generic.reviewerRecommendationManager.settings.enabled"}</span>
						</label>
					</div>

				</div>
			{/foreach}
		</div>
	{/fbvFormArea}

	{fbvFormButtons}

	<p class="rrmHint">{translate key="plugins.generic.reviewerRecommendationManager.settings.disableNote"}</p>
</form>
