const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const fs = require('node:fs');
(async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        const wrap = inner => '<div class="woocommerce woocommerce-page woocommerce-account ishi-theme-account ishi-customer-addresses" data-ishi-rest-nonce="rest-nonce"><div class="woocommerce-MyAccount-content">' + inner + '</div></div>';
        const cards = wrap('<div class="woocommerce-Addresses">Updated address' + ['billing','shipping'].map(type => '<button disabled type="button" data-ishi-address-control data-ishi-address-edit data-ishi-address-url="/wp-json/ishi-profile/v1/addresses/' + type + '">Edit ' + type + '</button>').join('') + '</div>');
        const editor = type => wrap('<form data-ishi-address-form data-ishi-address-url="/wp-json/ishi-profile/v1/addresses/' + type + '" onsubmit="return false;"><fieldset data-ishi-address-ready disabled><input name="action" value="ishi_save_customer_address" type="hidden"><input name="' + type + '_city" value="Makati"><button type="button" data-ishi-address-save name="ishi_save_address" value="' + type + '">Save changes</button></fieldset></form>');
        let documents = 0, posts = 0;
        await page.route('https://example.test/**', async route => {
            const req = route.request();
            if (req.url().includes('/wp-json/')) {
                assert.equal(req.headers()['x-wp-nonce'], 'rest-nonce');
                const type = req.url().endsWith('shipping') ? 'shipping' : 'billing';
                const saved = req.method() === 'POST';
                if (saved) { posts++; assert.match(req.postData(), /ishi_save_customer_address/); }
                return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ishi_addresses: true, saved: saved ? true : null, html: saved ? cards : editor(type) }) });
            }
            documents++;
            return route.fulfill({ contentType: 'text/html', body: '<details open><summary>Third panel</summary>' + cards + '</details>' });
        });
        await page.goto('https://example.test/dashboard/#third');
        await page.addScriptTag({ content: fs.readFileSync(require('node:path').join(__dirname, '../assets/address-navigation.js'), 'utf8') });
        for (const type of ['billing','shipping']) {
            await page.getByRole('button', { name: 'Edit ' + type }).click();
            await page.locator('form').waitFor();
            assert.equal(await page.evaluate(() => document.querySelector('form').action instanceof HTMLInputElement), true);
            await page.getByRole('button', { name: 'Save changes' }).click();
            await page.locator('.woocommerce-Addresses').waitFor();
            assert.equal(page.url(), 'https://example.test/dashboard/#third');
            assert.equal(await page.locator('details').evaluate(node => node.open), true);
        }
        assert.equal(posts, 2); assert.equal(documents, 1);
        console.log('PASS Chromium: billing and shipping Edit/Save via REST; only initial document request; parent remains open.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
