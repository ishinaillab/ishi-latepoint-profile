const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const fs = require('node:fs');
(async () => {
    const browser = await chromium.launch(process.env.ISHI_BROWSER_CHANNEL ? {channel:process.env.ISHI_BROWSER_CHANNEL} : {});
    try {
        const page = await browser.newPage();
        const form = (saved = false) => '<div class="ishi-theme-account" data-ishi-ui="profile" data-ishi-rest-nonce="rest">' +
            (saved ? '<div role="status">Your changes have been saved.</div>' : '') +
            '<form data-ishi-ui-form data-ishi-ui-url="/wp-json/ishi-profile/v1/profile" method="post" onsubmit="return false;">' +
            '<label for="name">Name</label><input data-ishi-ui-ready disabled id="name" name="account_first_name" value="' + (saved ? 'Updated' : 'Before') + '">' +
            '<input type="password" data-ishi-ui-ready disabled name="password_current">' +
            '<button type="button" data-ishi-ui-ready disabled data-ishi-ui-save>Save changes</button></form></div>';
        let documents = 0, posts = 0;
        await page.route('https://example.test/**', async route => {
            const req = route.request();
            if (req.url().includes('/wp-json/')) {
                posts++; assert.equal(req.method(),'POST'); assert.equal(req.headers()['x-wp-nonce'],'rest');
                assert.match(req.postData(),/Updated/);
                await new Promise(r=>setTimeout(r,100));
                return route.fulfill({contentType:'application/json',body:JSON.stringify({ishi_ui:true,component:'profile',saved:true,html:form(true),rest_nonce:'rest'})});
            }
            documents++;
            return route.fulfill({contentType:'text/html',body:'<details open><summary>Third tab</summary>'+form()+'</details>'});
        });
        await page.goto('https://example.test/sample/#third');
        await page.addScriptTag({content:fs.readFileSync(require('node:path').join(__dirname,'../assets/shortcode-ui.js'),'utf8')});
        await page.getByLabel('Name').fill('Updated');
        await page.getByRole('button',{name:'Save changes'}).focus();
        await page.keyboard.press('Enter');
        await page.getByRole('status').waitFor();
        assert.equal(await page.getByLabel('Name').inputValue(),'Updated');
        assert.equal(await page.locator('details').evaluate(n=>n.open),true);
        assert.equal(page.url(),'https://example.test/sample/#third');
        assert.equal(documents,1); assert.equal(posts,1);
        console.log('PASS Chromium: Profile keyboard save updates only its root; one document request; parent remains open.');
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
