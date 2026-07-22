/**
 * @file cypress/tests/functional/ReviewerRecommendationManager.cy.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Reviewer Recommendation Manager plugin tests', function() {
	const settingsForm = 'form[id="reviewerRecommendationManagerSettingsForm"]';

	const openPluginSettings = () => {
		cy.get('button[id="plugins-button"]').click();
		cy.get('tr[id*="reviewerrecommendationmanagerplugin"] a.show_extras').click();
		cy.get('a[id*="reviewerrecommendationmanagerplugin-settings"]').click();
		cy.waitJQuery();
	};

	it('Renames and disables a reviewer recommendation, and persists the settings', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		cy.get('nav').contains('Settings').click();
		// Ensure submenu item click despite animation
		cy.get('nav').contains('Website').click({ force: true });
		cy.get('button[id="plugins-button"]').click();

		// Find and enable the plugin
		cy.get('input[id^="select-cell-reviewerrecommendationmanagerplugin-enabled"]').click();
		cy.contains('has been enabled');
		cy.waitJQuery();

		// Open the plugin settings
		openPluginSettings();

		// The original OJS wording is shown as a fixed reference anchor
		cy.get(settingsForm).contains('Accept Submission');

		// Rename recommendation 1 and disable recommendation 6
		cy.get(settingsForm + ' input[id^="label_1-en-"]')
			.clear()
			.type('Accept as is', { delay: 0 });
		cy.get(settingsForm + ' input[name="enabled_6"]').uncheck({ force: true });
		cy.get(settingsForm + ' button[id^="submitFormButton-"]').click({ force: true });
		cy.waitJQuery();

		// Reopen the settings: both changes must have been persisted
		cy.reload();
		cy.waitJQuery();
		openPluginSettings();
		cy.get(settingsForm + ' input[id^="label_1-en-"]').should('have.value', 'Accept as is');
		cy.get(settingsForm + ' input[name="enabled_6"]').should('not.be.checked');

		// Restore the defaults so the test is repeatable
		cy.get(settingsForm + ' input[id^="label_1-en-"]').clear();
		cy.get(settingsForm + ' input[name="enabled_6"]').check({ force: true });
		cy.get(settingsForm + ' button[id^="submitFormButton-"]').click({ force: true });
		cy.waitJQuery();
	});
})
