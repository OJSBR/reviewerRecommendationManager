/**
 * @file plugins/generic/reviewerRecommendationManager/js/settingsForm.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Drag-and-drop ordering and the enabled state of the recommendation cards in
 * the settings form. Called by the form template once the modal is shown.
 */
(function ($) {
	'use strict';

	window.ojsbrReviewerRecommendationSettings = function (listSelector) {
		var $list = $(listSelector);

		function renumber() {
			$list.children('.rrmCard').each(function (i) {
				$(this).find('.rrmOrder').val(i + 1);
				$(this).find('.rrmPos').text(i + 1);
			});
		}

		if ($.fn.sortable) {
			$list.sortable({
				handle: '.rrmHandle',
				items: '> .rrmCard',
				axis: 'y',
				containment: 'parent',
				tolerance: 'pointer',
				placeholder: 'rrmPlaceholder',
				forcePlaceholderSize: true,
				update: renumber
			});
		}

		function sync($checkbox) {
			$checkbox.closest('.rrmCard').toggleClass('rrmCard--off', !$checkbox.prop('checked'));
		}
		$list.on('change', '.rrmToggleInput', function () {
			sync($(this));
		});
		$list.find('.rrmToggleInput').each(function () {
			sync($(this));
		});

		renumber();
	};
})(jQuery);
