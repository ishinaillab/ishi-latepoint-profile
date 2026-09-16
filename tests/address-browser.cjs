const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const fs = require('node:fs');
(async () => {
    const browser = await chromium.launch(process.env.ISHI_BROWSER_CHANNEL ? { channel: process.env.ISHI_BROWSER_CHANNEL } : {});
    try {
        const page = await browser.newPage();
        const wrap = inner => '<div class="woocommerce woocommerce-page woocommerce-account ishi-theme-account ishi-customer-addresses" data-ishi-ui="addresses" data-ishi-rest-nonce="rest-nonce"><div class="woocommerce-MyAccount-content">' + inner + '</div></div>';
        const cards = wrap('<div class="woocommerce-Addresses" data-ishi-ui-view>Updated address' + ['billing','shipping'].map(type => '<button disabled type="button" data-ishi-ui-control data-ishi-ui-edit data-ishi-ui-url="/wp-json/ishi-profile/v1/addresses/' + type + '">Edit ' + type + '</button>').join('') + '</div>');
        const editor = type => wrap('<form data-ishi-ui-form data-ishi-ui-url="/wp-json/ishi-profile/v1/addresses/' + type + '" onsubmit="return false;"><fieldset data-ishi-ui-ready disabled><div class="woocommerce-address-fields"><select class="country_select" name="country"><option selected value="PH">Philippines</option></select><select class="state_select" name="state"><option selected value="00">Metro Manila</option></select></div><input name="action" value="ishi_save_customer_address" type="hidden"><input name="' + type + '_city" value="Makati"><button type="button" data-ishi-ui-save name="ishi_save_address" value="' + type + '">Save changes</button></fieldset></form>');
        let documents = 0, posts = 0;
        await page.route('https://example.test/**', async route => {
            const req = route.request();
            if (req.url().includes('/wp-json/')) {
                assert.equal(req.headers()['x-wp-nonce'], 'rest-nonce');
                const type = req.url().endsWith('shipping') ? 'shipping' : 'billing';
                const saved = req.method() === 'POST';
                if (saved) { await new Promise(resolve => setTimeout(resolve, 250)); posts++; assert.match(req.postData(), /ishi_save_customer_address/); }
                return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ishi_ui: true, component: 'addresses', ishi_addresses: true, saved: saved ? true : null, html: saved ? cards : editor(type) }) });
            }
            documents++;
            return route.fulfill({ contentType: 'text/html', body: '<details open><summary>Third panel</summary>' + cards + '</details>' });
        });
        await page.goto('https://example.test/dashboard/#third');
        await page.addScriptTag({ content: fs.readFileSync(require('node:path').join(__dirname, '../assets/shortcode-ui.js'), 'utf8') });
        await page.addStyleTag({ content: 'select:not(.esg-sorting-select):not([class*="trx_addons_attrib_"]){visibility:hidden}' });
        await page.addStyleTag({ content: fs.readFileSync(require('node:path').join(__dirname, '../assets/theme-compatibility.css'), 'utf8') });
        for (const type of ['billing','shipping']) {
            await page.getByRole('button', { name: 'Edit ' + type }).click();
            await page.locator('form').waitFor();
            for (const [cls, value] of [['country_select', 'PH'], ['state_select', '00']]) {
                const select = page.locator('select.' + cls);
                assert.equal(await select.inputValue(), value);
                // Without theme/SelectWoo initialization, the theme's hiding rule must win.
                assert.equal(await select.evaluate(el => getComputedStyle(el).visibility), 'hidden');
            }
            assert.equal(await page.evaluate(() => document.querySelector('form').action instanceof HTMLInputElement), true);
            await page.getByRole('button', { name: 'Save changes' }).click();
            const save = page.locator('[data-ishi-ui-save]');
            assert.equal(await save.getAttribute('aria-disabled'), 'true');
            assert.equal(await save.evaluate(el => el.disabled), false);
            await save.evaluate(el => { el.click(); el.closest('form').dispatchEvent(new Event('submit', {bubbles:true,cancelable:true})); });
            await page.locator('.woocommerce-Addresses').waitFor();
            assert.equal(page.url(), 'https://example.test/dashboard/#third');
            assert.equal(await page.locator('details').evaluate(node => node.open), true);
        }
        assert.equal(posts, 2); assert.equal(documents, 1);
        console.log('PASS Chromium: billing and shipping Edit/Save via REST; only initial document request; parent remains open.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
