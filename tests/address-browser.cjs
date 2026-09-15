const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const fs = require('node:fs');
(async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        const editor = '<div class="ishi-customer-addresses"><form data-ishi-address-form action="/dashboard/?ishi_address=billing" onsubmit="return false;"><fieldset data-ishi-address-ready disabled><input name="action" value="ishi_save_customer_address" type="hidden"><input name="billing_city" value="Makati"><button type="button" data-ishi-address-save name="ishi_save_address" value="billing">Save changes</button></fieldset></form></div>';
        const cards = '<div class="ishi-customer-addresses"><div class="woocommerce-Addresses">Updated address</div></div>';
        let posts = 0;
        await page.route('https://example.test/**', async route => {
            if (route.request().method() === 'POST') {
                posts++;
                assert.equal(route.request().headers()['x-ishi-address-request'], '1');
                assert.match(route.request().postData(), /ishi_save_customer_address/);
                return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ishi_addresses: true, saved: true, html: cards }) });
            }
            return route.fulfill({ contentType: 'text/html', body: '<details open><summary>Third panel</summary>' + editor + '</details>' });
        });
        await page.goto('https://example.test/dashboard/#third');
        // Verify the actual browser behavior that the old jsdom fixture missed.
        assert.equal(await page.evaluate(() => document.querySelector('form').action instanceof HTMLInputElement), true);
        await page.addScriptTag({ content: fs.readFileSync(require('node:path').join(__dirname, '../assets/address-navigation.js'), 'utf8') });
        await page.getByRole('button', { name: 'Save changes' }).click();
        await page.locator('.woocommerce-Addresses').waitFor();
        assert.equal(posts, 1);
        assert.equal(page.url(), 'https://example.test/dashboard/#third');
        assert.equal(await page.locator('details').evaluate(node => node.open), true);
        console.log('PASS Chromium: named action control, confirmed save, cards restored, parent open, no page navigation');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
