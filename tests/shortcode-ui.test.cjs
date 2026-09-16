const {test} = require('node:test');
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const script = require('node:fs').readFileSync(require('node:path').join(__dirname, '../assets/shortcode-ui.js'), 'utf8');
const tick = () => new Promise(r => setTimeout(r, 25));
const form = '<form data-ishi-ui-form data-ishi-ui-url="/wp-json/ishi-profile/v1/profile" onsubmit="return false;"><p data-ishi-ui-unavailable role="status">Loading</p><label for="name">Name</label><input data-ishi-ui-ready disabled id="name" name="account_first_name" value="Retained"><input data-ishi-ui-ready disabled type="password" name="password_current" value="secret"><button type="button" data-ishi-ui-ready disabled data-ishi-ui-save>Save changes</button></form>';
const wrap = (component, inner = form) => '<div class="ishi-theme-account" data-ishi-ui="' + component + '" data-ishi-rest-nonce="old">' + inner + '</div>';
function setup(component = 'profile', inner = form) {
    const w = new JSDOM('<details open>' + wrap(component, inner) + '</details><div id="other">' + wrap('addresses', '<div data-ishi-ui-view>Unchanged</div>') + '</div>', {url:'https://example.test/page/#third', runScripts:'outside-only'}).window;
    w.fetch = async () => {throw Error('network');}; w.eval(script); return w;
}
function reply(component = 'profile', saved = true, inner = form) {
    return {ok: saved, status: saved ? 200 : 422, url:'https://example.test/wp-json/ishi-profile/v1/profile',
        json: async () => ({ishi_ui:true, component, saved, rest_nonce:'renewed', html:wrap(component, inner)})};
}
test('profile saves in place, refreshes all REST nonces and focuses visible feedback', async () => {
    const w = setup(); const parent = w.document.querySelector('details'); const root = parent.firstChild;
    w.fetch = async (_, options) => {
        assert.equal(options.body.get('account_first_name'), 'Retained');
        assert.equal(options.body.get('password_current'), 'secret');
        assert.equal(options.redirect, 'error');
        return reply('profile', true, '<div role="status">Saved</div>' + form.replace('value="secret"', 'value=""'));
    };
    root.querySelector('button').click(); await tick();
    assert.equal(parent.firstChild, root); assert.equal(parent.open, true);
    assert.equal(w.location.href, 'https://example.test/page/#third');
    assert.equal(w.document.activeElement.textContent, 'Saved');
    assert.equal(w.document.querySelector('#other').textContent, 'Unchanged');
    assert.equal(w.document.querySelector('#other [data-ishi-ui]').dataset.ishiRestNonce, 'renewed');
    assert.equal(root.querySelector('input[type=password]').value, '');
    assert.equal(root.querySelector('label').htmlFor, root.querySelector('input').id);
    assert.equal(root.querySelector('button').textContent, 'Save changes');
    w.close();
});
test('profile failure retains values, clears passwords and permits corrected submission', async () => {
    const w = setup(); w.fetch = async () => reply('profile', false, '<div role="alert">Correct name</div>' + form.replace('value="secret"', 'value=""'));
    w.document.querySelector('button').click(); await tick();
    assert.equal(w.document.querySelector('input').value, 'Retained');
    assert.equal(w.document.querySelector('input[type=password]').value, '');
    assert.equal(w.document.querySelector('button').disabled, false);
    assert.equal(w.document.activeElement.textContent, 'Correct name'); w.close();
});
test('unknown response component and missing confirmation cannot replace a profile', async () => {
    for (const result of [reply('addresses'), reply('profile', undefined)]) {
        const w = setup(); if (result === undefined) continue;
        // Explicitly test the missing saved flag, independent of helper defaults.
        if (result === null) continue;
        w.fetch = async () => result;
        if (result.status === 200 && (await result.json()).component === 'profile') {
            const data = await result.json(); delete data.saved; result.json = async () => data;
        }
        w.document.querySelector('button').click(); await tick();
        assert.ok(w.document.querySelector('[data-ishi-navigation-error]'));
        assert.equal(w.document.querySelector('input[type=password]').value, '');
        assert.equal(w.document.querySelector('input').value, 'Retained'); w.close();
    }
});
test('future component uses the same flow without a component-specific listener', async () => {
    const w = setup('preferences'); let calls = 0;
    w.fetch = async () => { calls++; return reply('preferences', true, '<div data-ishi-ui-view>Updated preferences</div>'); };
    w.document.querySelector('button').click();
    w.document.querySelector('form').dispatchEvent(new w.Event('submit', {bubbles:true, cancelable:true}));
    await tick(); assert.equal(calls, 1); assert.equal(w.document.querySelector('details').textContent, 'Updated preferences');
    assert.equal(w.document.querySelector('details').open, true); w.close();
});
test('network failure clears secrets without retry or navigation', async () => {
    const w = setup(); const original = w.location.href; let calls=0;
    w.fetch = async () => {calls++; throw Error('offline');};
    w.document.querySelector('button').click(); await tick();
    assert.equal(calls,1); assert.equal(w.location.href,original);
    assert.equal(w.document.querySelector('input[type=password]').value,'');
    assert.equal(w.document.querySelector('input').value,'Retained'); w.close();
});
test('new fragments initialize Qwery before WooCommerce and only inside the root', async () => {
    const w = setup(); const calls=[]; const root=w.document.querySelector('[data-ishi-ui]');
    w.QWERY_STORAGE={};
    w.jQuery = node => ({node, trigger:(event,args)=>calls.push([event,args]), find:()=>({trigger:event=>calls.push([event,node])})});
    w.fetch=async()=>reply(); w.document.querySelector('button').click(); await tick();
    assert.equal(calls[0][0],'action.init_hidden_elements'); assert.equal(calls[0][1][0].node,root);
    assert.equal(calls[1][0],'refresh'); assert.equal(calls[1][1],root); w.close();
});
