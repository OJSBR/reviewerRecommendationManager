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
			$list.children('.rrmItem').each(function(i) {ldelim}
				$(this).find('.rrmOrder').val(i + 1);
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
		rrmRenumber();
	{rdelim});
</script>

<style>
	#rrmSortable {ldelim} list-style:none; margin:0; padding:0; {rdelim}
	.rrmItem {ldelim} display:flex; align-items:flex-start; gap:1em; padding:1em; margin:.5em 0; border:1px solid #ddd; border-radius:4px; background:#fff; {rdelim}
	.rrmHandle {ldelim} cursor:move; color:#999; font-size:1.3em; line-height:1; padding-top:.2em; user-select:none; {rdelim}
	.rrmHandle:hover {ldelim} color:#555; {rdelim}
	.rrmItem.ui-sortable-helper {ldelim} box-shadow:0 4px 12px rgba(0,0,0,.15); {rdelim}
	.rrmPlaceholder {ldelim} border:2px dashed #bbb; border-radius:4px; margin:.5em 0; background:#f4f4f4; {rdelim}
	.rrmBody {ldelim} flex:1 1 auto; {rdelim}
	.rrmOriginal {ldelim} display:inline-flex; align-items:center; gap:.5em; margin:0 0 .8em; padding:.3em .7em; background:#eef3fb; border-left:4px solid #3a6ea5; border-radius:3px; {rdelim}
	.rrmOriginalTag {ldelim} font-size:.75em; text-transform:uppercase; letter-spacing:.04em; color:#3a6ea5; font-weight:600; {rdelim}
	.rrmOriginalText {ldelim} font-size:1.05em; color:#12263a; {rdelim}
	.rrmRow {ldelim} display:flex; flex-wrap:wrap; gap:1.5em; align-items:flex-start; {rdelim}
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

	<div class="pkpNotification pkpNotification--warning" style="margin:1em 0; padding:1em; border:1px solid #e0c200; background:#fff8d5;">
		<p><strong>{translate key="plugins.generic.reviewerRecommendationManager.settings.historyNotice.title"}</strong><br />
		{translate key="plugins.generic.reviewerRecommendationManager.settings.historyNotice.body"}</p>
	</div>

	<p style="color:#777;">{translate key="plugins.generic.reviewerRecommendationManager.settings.dragHint"}</p>

	{fbvFormArea id="reviewerRecommendationManagerArea"}
		<ol id="rrmSortable">
			{foreach from=$recommendations item=rec}
				<li class="rrmItem" data-code="{$rec.code}">
					<span class="rrmHandle" title="{translate key="plugins.generic.reviewerRecommendationManager.settings.dragHint"}">&#9776;</span>
					<input type="hidden" name="order_{$rec.code}" value="{$rec.order|escape}" class="rrmOrder" />
					<div class="rrmBody">
						<div class="rrmOriginal">
							<span class="rrmOriginalTag">{translate key="plugins.generic.reviewerRecommendationManager.settings.originalLabel"}</span>
							<strong class="rrmOriginalText">{$rec.original|escape}</strong>
						</div>
						<div class="rrmRow">

							<div style="flex:1 1 320px; min-width:280px;">
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

							<div style="flex:0 0 200px; padding-top:1.6em;">
								{fbvElement
									type="checkbox"
									name="enabled_`$rec.code`"
									id="enabled_`$rec.code`"
									value="1"
									checked=$rec.enabled
									label="plugins.generic.reviewerRecommendationManager.settings.enabled"
								}
							</div>

							<div style="flex:1 1 220px; padding-top:1.6em;">
								{if $rec.usage > 0}
									<span class="pkp_form_error" style="color:#d00;">
										{translate key="plugins.generic.reviewerRecommendationManager.settings.usageWarning" usage=$rec.usage}
									</span>
								{else}
									<span style="color:#777;">
										{translate key="plugins.generic.reviewerRecommendationManager.settings.usageSafe"}
									</span>
								{/if}
							</div>

						</div>
					</div>
				</li>
			{/foreach}
		</ol>
	{/fbvFormArea}

	{fbvFormButtons}

	<p><span class="formRequired">{translate key="plugins.generic.reviewerRecommendationManager.settings.disableNote"}</span></p>
</form>
