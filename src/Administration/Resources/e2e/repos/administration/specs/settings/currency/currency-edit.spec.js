const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'currency-edit', 'currency', 'edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('currency').then(() => {
            done();
        });
    },
    'open currency module and look for currency to be edited': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 5,
                subMenuItemPath: '#/sw/settings/currency/index',
                subMenuTitle: 'Currencies'
            });

        browser.expect.element(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'edit currency': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-currency-list__edit-action', '.sw-context-button__button', `${page.elements.gridRow}--3`)
            .waitForElementVisible('.sw-settings-currency-detail .sw-card__content')
            .clearValue('input[name=sw-field--currency-name]')
            .fillField('input[name=sw-field--currency-name]', 'Yen but true', true)
            .waitForElementPresent(page.elements.currencySaveAction)
            .click(page.elements.currencySaveAction)
            .checkNotification('Currency "Yen but true" has been saved successfully.')
            .assert.urlContains('#/sw/settings/currency/detail');
    },
    'verify edited currency': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a.smart-bar__back-btn')
            .expect.element(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`).to.have.text.that.contains('Yen but true').before(browser.globals.waitForConditionTimeout);
    },
    after: (browser) => {
        browser.end();
    }
};
