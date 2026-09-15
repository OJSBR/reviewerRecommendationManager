{**
 * plugins/generic/reviewerRecommendationManager/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Reviewer recommendation settings (rename / reorder / disable).
 *
 * NOTE: the checkbox is written by hand on purpose. PKP's {fbvElement type="checkbox"}
 * renders a bare <li> (lib/pkp/templates/form/checkbox.tpl) and is only valid inside
 * {fbvFormSection list=true}, which wraps it in a <ul>. Inside the cards that <li> was
 * hoisted out by the HTML parser and ended up attached to the next item.
 *}
<link rel="stylesheet" href="{$rrmStyleUrl|escape}">
<script>
	$(function() {ldelim}
		$('#reviewerRecommendationManagerSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		window.ojsbrReviewerRecommendationSettings('#rrmSortable');
	{rdelim});
</script>

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
						<span class="rrmHandle" title="{"plugins.generic.reviewerRecommendationManager.settings.dragHint"|translate|escape}">&#10303;</span>
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
