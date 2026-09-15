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
 * without them. Captcha on login must be off for the run. The defaults match the
 * data set of PKP's continuous integration, and the first test enables the plugin
 * when it is off. Selectors use names and ids and assertions use
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

	// ---- OJSBR spec helpers (padrão v2): work on OJS/OMP 3.3, 3.4 and 3.5 and in PKP's CI ----

	const pageUrl = (path) => '/index.php/' + contextPath + (path ? '/' + path : '');

	// Same as PKP's cy.waitJQuery(), which the support files of OJS 3.3 test sites may lack.
	const waitJQuery = () => cy.window().its('jQuery.active').should('eq', 0);

	// Requests carry the browser's User-Agent: OJS 3.3 drops a session whose agent changes.
	const request = (options) => cy.window({log: false}).then((win) => cy.request(Object.assign(
		typeof options === 'string' ? {url: options} : options,
		{headers: Object.assign({'User-Agent': win.navigator.userAgent}, (typeof options === 'string' ? {} : options.headers) || {})}
	)));

	// Signs in through requests (the login page can re-render while it is typed into), then
	// falls back to the form when the session did not stick (OJS 3.3 cookie handling).
	const login = (username, password) => {
		cy.clearCookies();
		request(pageUrl('login')).then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			// The form posts to the URL with the language: a redirect would turn the POST into a GET.
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			request({method: 'POST', url: action, form: true, body: {csrfToken: token, username: username, password: password}, log: false});
		});
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		cy.get('body').then(($body) => {
			if ($body.find('form#login').length) {
				cy.get('form#login input[name="username"]').type(username, {delay: 0});
				cy.get('form#login input[name="password"]').type(password, {delay: 0, log: false});
				cy.get('form#login').submit();
				cy.get('form#login', {timeout: 30000}).should('not.exist');
			}
		});
	};

	// REST API calls made from the page itself, so they carry the browser's own session.
	const api = (path, options = {}) => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(path, Object.assign({credentials: 'same-origin'}, options)).then((response) => {
			if (!response.ok) {
				return response.text().then((text) => {
					throw new Error(path + ' answered ' + response.status + ': ' + text.slice(0, 300));
				});
			}
			return response.json();
		}),
		{log: false, timeout: 30000}
	));

	// The website settings page on its Plugins tab (a new query string forces a load).
	const openPluginsTab = () => {
		cy.visit(pageUrl('management/settings/website') + '?reload=' + Date.now() + '#plugins');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		cy.get('button[id="plugins-button"]').should('have.attr', 'aria-selected', 'true');
		waitJQuery();
	};

	// Enables the plugin in the grid when it is off (never turns it off).
	const enablePlugin = (rowName) => {
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).then(($checkbox) => {
			if (!$checkbox.is(':checked')) {
				cy.wrap($checkbox).click();
				waitJQuery();
			}
		});
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]').should('be.checked');
	};

	// Opens the settings modal from the grid, without reloading the page: a reload right
	// after saving can stall the web server of PKP's CI. The form is fetched each time.
	const openPluginSettings = (rowName, formSelector) => {
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]', {timeout: 30000}).then(($link) => {
			if (!$link.is(':visible')) {
				cy.get('tr[id$="-row-' + rowName + '"] a.show_extras').first().click();
			}
		});
		// The grid may still be animating the extras row: the link is clicked once it exists.
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]').first().click({force: true});
		waitJQuery();
		cy.window().should((win) => {
			expect(win.jQuery(formSelector).data('pkp.handler')).to.exist;
		});
	};

	// ---- end of helpers ----

	const openSettings = () => openPluginSettings('reviewerrecommendationmanagerplugin', settingsForm);

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
		waitJQuery();
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
			openPluginsTab();
			enablePlugin('reviewerrecommendationmanagerplugin');
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
			openPluginsTab();
			openSettings();
			setLabel(1, '<b>Accept as is</b> {{7*7}}');
			cy.get(enabled(6)).uncheck({force: true});
			save();

			openSettings();
			cy.get(label(1)).should('have.value', 'Accept as is { {7*7} }');
			cy.get(enabled(6)).should('not.be.checked');
		});
	});

	// Defined only with a reviewer and an assignment: this.skip() breaks PKP's failed-log hook.
	(reviewerUser && reviewerPassword && reviewSubmissionId ? describe : describe.skip)('The reviewer form', function() {

		it('Offers the renamed option and hides the disabled one', function() {
			login(reviewerUser, reviewerPassword);
			request(pageUrl('reviewer/step/' + reviewSubmissionId + '?step=3')).then((response) => {
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
			openPluginsTab();
			openSettings();
			applyState(original);
			save();
		}
	});
});
