/**
 * @file cypress/tests/functional/ReviewerRecommendationManager.cy.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Functional tests: the settings, and the list a reviewer actually receives.
 *
 * Parameters (--env): contextPath, adminUser, adminPassword (a journal manager),
 * formLocale (a form language of the journal, default en). For the reviewer
 * test, also reviewerUser, reviewerPassword and reviewSubmissionId: a submission
 * with a review assignment of that reviewer already at step 3; it is skipped
 * without them. Captcha on
 * login must be off for the run.
 *
 * The plugin must be enabled. Selectors use names and ids and assertions use
 * recommendation codes, so the spec runs against a journal in any language.
 * Every setting touched is put back as it was at the end.
 */

describe('Reviewer Recommendation Manager plugin', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';
	const formLocale = Cypress.env('formLocale') || 'en';
	const reviewerUser = Cypress.env('reviewerUser');
	const reviewerPassword = Cypress.env('reviewerPassword');
	const reviewSubmissionId = Cypress.env('reviewSubmissionId');

	const settingsForm = 'form[id="reviewerRecommendationManagerSettingsForm"]';
	const label = (code) => settingsForm + ' input[name="label_' + code + '[' + formLocale + ']"]';
	const enabled = (code) => settingsForm + ' input[name="enabled_' + code + '"]';

	const login = (username, password) => {
		cy.clearCookies();
		cy.visit('/index.php/' + contextPath + '/login');
		cy.get('input[id=username]').clear().type(username, {delay: 0});
		cy.get('input[id=password]').clear().type(password, {delay: 0, log: false});
		cy.get('form[id=login] button').click();
		cy.get('form[id=login]', {timeout: 30000}).should('not.exist');
	};

	const openSettings = () => {
		cy.visit('/index.php/' + contextPath + '/management/settings/website');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		cy.waitJQuery();
		cy.get('tr[id*="reviewerrecommendationmanagerplugin"] a.show_extras', {timeout: 30000}).click();
		cy.get('a[id*="reviewerrecommendationmanagerplugin-settings"]', {timeout: 30000}).click();
		cy.waitJQuery();
		cy.get(settingsForm, {timeout: 30000}).should('exist');
	};

	// The multilingual field keeps its value through clear() on some versions:
	// empty it explicitly before typing.
	const setLabel = (code, text) => {
		cy.get(label(code)).invoke('val', '').trigger('input');
		if (text) {
			cy.get(label(code)).type(text, {delay: 0, parseSpecialCharSequences: false});
		}
		cy.get(label(code)).should('have.value', text || '');
	};

	const save = () => {
		cy.get(settingsForm + ' button[id^="submitFormButton-"]').click({force: true});
		cy.waitJQuery();
		cy.get(settingsForm).should('not.exist');
	};

	// The settings found at the start, restored at the end.
	let original = null;

	const readState = () => {
		const state = {};
		[1, 2, 3, 4, 5, 6].forEach((code) => {
			cy.get(label(code)).invoke('val').then((value) => { state['label_' + code] = value; });
			cy.get(enabled(code)).then(($input) => { state['enabled_' + code] = $input.is(':checked'); });
		});
		return cy.wrap(state);
	};

	const applyState = (state) => {
		[1, 2, 3, 4, 5, 6].forEach((code) => {
			setLabel(code, state['label_' + code]);
			cy.get(enabled(code))[state['enabled_' + code] ? 'check' : 'uncheck']({force: true});
		});
	};

	describe('Settings', function() {
		it('Lists the six recommendations with the core wording as a fixed reference', function() {
			login(adminUser, adminPassword);
			openSettings();
			cy.get(settingsForm + ' .rrmCard').should('have.length', 6);
			[1, 2, 3, 4, 5, 6].forEach((code) => {
				cy.get(settingsForm + ' .rrmCard[data-code="' + code + '"] .rrmIdentText').invoke('text').should('not.be.empty');
				cy.get(settingsForm + ' input[name="order_' + code + '"]').should('exist');
			});
			readState().then((state) => { original = state; });
		});

		it('Saves a renamed and a disabled recommendation, without markup', function() {
			login(adminUser, adminPassword);
			openSettings();
			setLabel(1, '<b>Accept as is</b> {{7*7}}');
			cy.get(enabled(6)).uncheck({force: true});
			save();

			openSettings();
			cy.get(label(1)).should('have.value', 'Accept as is { {7*7} }');
			cy.get(enabled(6)).should('not.be.checked');
		});
	});

	describe('The reviewer form', function() {
		before(function() {
			if (!reviewerUser || !reviewerPassword || !reviewSubmissionId) {
				this.skip();
			}
		});

		it('Offers the renamed option and hides the disabled one', function() {
			login(reviewerUser, reviewerPassword);
			cy.request('/index.php/' + contextPath + '/reviewer/step/' + reviewSubmissionId + '?step=3').then((response) => {
				expect(response.status).to.eq(200);
				const html = Cypress.$('<div>').html(response.body.content);
				const options = html.find('select[name="recommendation"] option');
				const codes = options.map((i, option) => option.value).get();

				expect(codes).to.include('1');
				expect(codes, 'the disabled recommendation').to.not.include('6');
				expect(options.filter('[value="1"]').text().trim()).to.eq('Accept as is { {7*7} }');
				expect(html.find('select[name="recommendation"] b, select[name="recommendation"] script')).to.have.length(0);
			});
		});
	});

	after(function() {
		if (original) {
			login(adminUser, adminPassword);
			openSettings();
			applyState(original);
			save();
		}
	});
});
