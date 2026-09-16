const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
(async () => {
    const browser = await chromium.launch(process.env.ISHI_BROWSER_CHANNEL ? {channel:process.env.ISHI_BROWSER_CHANNEL} : {});
    try {
        const page = await browser.newPage({viewport:{width:1200,height:1000}});
        await page.route('**/*', route => route.abort());
        const cards = ['Billing address','Shipping address'].map((title,i) =>
            '<div class="u-column'+(i+1)+' col-'+(i+1)+' woocommerce-Address ishi-ui-card"><header class="woocommerce-Address-title title ishi-ui-card-header"><h2>'+title+'</h2><button class="edit" data-ishi-ui-edit>Edit '+title+'</button></header><div class="ishi-ui-card-body"><address>Example customer<br>123 Example Street<br>Example City</address><p>Extension content</p></div></div>').join('');
        await page.setContent('<div id="scope" class="woocommerce woocommerce-page woocommerce-account ishi-theme-account"><div class="woocommerce-MyAccount-content"><div class="u-columns woocommerce-Addresses col2-set addresses ishi-ui-card-grid">'+cards+'</div></div></div>');
        if(process.env.ISHI_THEME_CSS) {
            await page.addStyleTag({content:fs.readFileSync(process.env.ISHI_THEME_CSS,'utf8')});
        } else {
            // Relevant native float context, including Qwery's more-specific column override.
            await page.addStyleTag({content:'.woocommerce-account .woocommerce-MyAccount-content .addresses.col2-set .col-1{float:left;width:48%}.woocommerce-account .woocommerce-MyAccount-content .addresses.col2-set .col-2{float:right;width:48%}.col2-set:before,.col2-set:after{content:" ";display:table}h2{font:42px/1.1 Georgia,serif;margin:.78em 0 .4em}address{margin-top:1em}'});
        }
        await page.addStyleTag({content:'body{margin:0}#scope{font-size:16px;max-width:100%}*,*:before,*:after{animation:none!important;transition:none!important}'});
        const fontBefore = await page.locator('h2').first().evaluate(n=>getComputedStyle(n).font);
        await page.addStyleTag({content:fs.readFileSync(path.join(__dirname,'../assets/theme-compatibility.css'),'utf8')});
        assert.equal(await page.locator('h2').first().evaluate(n=>getComputedStyle(n).font),fontBefore);
        let paired=0, stacked=0, asymmetric=0;
        for(const width of [1100,800,700,650,620,610,600,580,540,480,375,320]) {
            await page.locator('#scope').evaluate((n,w)=>n.style.width=w+'px',width);
            const data=await page.locator('#scope').evaluate(root=>{
                const rect=n=>{const r=n.getBoundingClientRect();return {top:r.top,left:r.left,height:r.height,right:r.right};};
                return {width:root.clientWidth,scroll:root.scrollWidth,cards:[...root.querySelectorAll('.ishi-ui-card')].map(n=>({card:rect(n),title:rect(n.querySelector('h2')),edit:rect(n.querySelector('button')),body:rect(n.querySelector('address'))}))};
            });
            assert.ok(data.scroll<=data.width+1,'Overflow at '+width);
            for(const card of data.cards) { assert.ok(card.edit.top>=card.title.top+card.title.height-1,'Heading/Edit overlap'); assert.ok(card.body.top>=card.edit.top+card.edit.height-1,'Edit/body overlap'); }
            if(Math.abs(data.cards[0].card.top-data.cards[1].card.top)<1) {
                paired++;
                if(Math.abs(data.cards[0].title.height-data.cards[1].title.height)>1) asymmetric++;
                assert.ok(Math.abs(data.cards[0].edit.top-data.cards[1].edit.top)<1,'Edit alignment at '+width);
                assert.ok(Math.abs(data.cards[0].body.top-data.cards[1].body.top)<1,'Address alignment at '+width);
            } else {stacked++; assert.ok(data.cards[1].card.top>=data.cards[0].card.top+data.cards[0].card.height-1);}
        }
        assert.ok(paired>0 && stacked>0);
        if(process.env.ISHI_LAYOUT_SCREENSHOT) { await page.locator('#scope').evaluate(n=>n.style.width='620px'); await page.screenshot({path:process.env.ISHI_LAYOUT_SCREENSHOT,fullPage:true}); }
        // Deliberately long translated heading and unbroken address token.
        await page.locator('#scope').evaluate(n=>{n.style.width='800px';n.querySelectorAll('h2')[1].textContent='Shipping address for deliveries to the registered customer';});
        const tops=await page.locator('.ishi-ui-card-body').evaluateAll(nodes=>nodes.map(n=>n.getBoundingClientRect().top));
        assert.ok(Math.abs(tops[0]-tops[1])<1);
        await page.locator('#scope').evaluate(n=>{n.style.width='320px';n.style.fontSize='32px';n.querySelector('address').textContent='LongAddressToken'.repeat(12);});
        assert.ok(await page.locator('#scope').evaluate(n=>n.scrollWidth<=n.clientWidth+1),'Enlarged text overflow');

        console.log('PASS card layout: '+paired+' paired widths, '+stacked+' stacked widths, '+asymmetric+' unequal-heading cases; long labels and enlarged text reflow.');
    } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

