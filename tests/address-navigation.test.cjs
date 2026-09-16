const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const script = fs.readFileSync(require('node:path').join(__dirname, '../assets/address-navigation.js'), 'utf8');
const cards = '<div class="woocommerce-Addresses"><h2>Addresses</h2><button type="button" disabled data-ishi-address-control data-ishi-address-edit data-ishi-address-url="/wp-json/ishi-profile/v1/addresses/billing">Edit</button></div>';
const editor = '<form data-ishi-address-form data-ishi-address-url="https://example.test/wp-json/ishi-profile/v1/addresses/billing"><h2>Billing</h2><p data-ishi-address-unavailable>Loading</p><fieldset data-ishi-address-ready disabled><input name="billing_city" value="Makati"><input type="hidden" name="action" value="ishi_save_customer_address"><input name="ishi_address_nonce" value="nonce"><button type="button" data-ishi-address-save name="ishi_save_address" value="billing">Save</button></fieldset></form>';
const html = inner => '<div class="ishi-customer-addresses" data-ishi-rest-nonce="rest-nonce">' + inner + '</div>';
function setup(inner = cards) {
    const dom = new JSDOM('<button aria-selected="true">Third tab</button><details open><summary>Addresses</summary><section id="third">' + html(inner) + '</section></details>', { url: 'https://example.test/dashboard/#third', runScripts: 'outside-only' });
    const w = dom.window;
    w.fetch = async () => { throw Error('No response'); };
    w.eval(script);
    return w;
}
const tick = () => new Promise(resolve => setTimeout(resolve, 20));
function response(inner, saving = false) { return { ok: true, redirected: saving, url: 'https://example.test/wp-json/ishi-profile/v1/addresses/billing', json: async () => ({ ishi_addresses: true, saved: saving, html: html(inner) }) }; }
function submit(w) { w.document.querySelector('form').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true })); }
test('edit and confirmed save replace only shortcode contents inside an open container', async () => {
    const w = setup(); const parent = w.document.querySelector('#third'); const root = parent.firstChild; const originalURL = w.location.href;
    w.fetch = async () => response(editor);
    w.document.querySelector('[data-ishi-address-edit]').click(); await tick();
    assert.ok(root.querySelector('form'));
    w.fetch = async (url, options) => { assert.equal(options.method, 'POST'); assert.equal(options.body.get('ishi_address_nonce'), 'nonce'); assert.equal(options.body.get('billing_city'), 'Makati'); return response(cards, true); };
    submit(w); await tick();
    assert.equal(parent.firstChild, root); assert.ok(root.querySelector('.woocommerce-Addresses'));
    assert.ok(w.document.querySelector('details').open); assert.equal(w.document.querySelector('button').getAttribute('aria-selected'), 'true'); assert.equal(w.location.href, originalURL);
    w.close();
});
test('validation failure keeps edit form and server-returned values', async () => {
    const w = setup(editor); w.fetch = async () => ({ ...response('<div role="alert">Required</div>' + editor.replace('Makati', 'Retained')), ok: false, status: 422 });
    submit(w); await tick(); assert.equal(w.document.querySelector('input').value, 'Retained'); assert.ok(w.document.querySelector('form')); w.close();
});
test('network failure retains entries, releases controls and never retries', async () => {
    const w = setup(editor); let count = 0; w.fetch = async () => { count++; throw Error('Offline'); };
    submit(w); await tick(); assert.equal(count, 1); assert.equal(w.document.querySelector('input').value, 'Makati'); assert.equal(w.document.querySelector('input').disabled, false); assert.ok(w.document.querySelector('[role="alert"]')); w.close();
});
test('double submission sends only one POST', async () => {
    const w = setup(editor); let count = 0; let finish;
    w.fetch = () => { count++; return new Promise(resolve => { finish = resolve; }); };
    submit(w); submit(w); assert.equal(count, 1); finish(response(cards, true)); await tick(); w.close();
});
test('unexpected login response does not replace the editor', async () => {
    const w = setup(editor); w.fetch = async () => ({ ...response(cards, true), url: 'https://example.test/wp-login.php' });
    submit(w); await tick(); assert.ok(w.document.querySelector('form')); assert.ok(w.document.querySelector('[role="alert"]')); w.close();
});
test('cards without a confirmed redirect are not treated as a successful save', async () => {
    const w = setup(editor); w.fetch = async () => response(cards);
    submit(w); await tick(); assert.ok(w.document.querySelector('form')); w.close();
});
test('fetched page scripts are not inserted', async () => {
    const w = setup(); w.fetch = async () => response(editor + '<script>window.unwanted = true;</script>');
    w.document.querySelector('[data-ishi-address-edit]').click(); await tick(); assert.equal(w.document.querySelector('script'), null); assert.equal(w.unwanted, undefined); w.close();
});

test('capture handler handles submit before parent handlers stop propagation', async () => {
    const w = setup(editor); let requests = 0;
    w.document.querySelector('#third').addEventListener('submit', event => { event.stopPropagation(); });
    w.fetch = async (url, options) => {
        requests++;
        assert.equal(options.headers['X-WP-Nonce'], 'rest-nonce');
        assert.equal(options.redirect, 'error');
        return response(cards, true);
    };
    const event = new w.Event('submit', { bubbles: true, cancelable: true });
    w.document.querySelector('form').dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    await tick(); assert.equal(requests, 1); assert.ok(w.document.querySelector('.woocommerce-Addresses')); w.close();
});
test('HTML instead of JSON never falls back to a normal form submission', async () => {
    const w = setup(editor); let calls = 0;
    w.fetch = async () => { calls++; return { ok: true, url: w.location.href, json: async () => { throw Error('HTML'); } }; };
    submit(w); await tick(); assert.equal(calls, 1); assert.ok(w.document.querySelector('form')); assert.ok(w.document.querySelector('[role="alert"]')); w.close();
});

test('Save button performs background POST without a native submit', async () => {
    const w = setup(editor); let requests = 0; let nativeSubmits = 0;
    w.document.querySelector('form').addEventListener('submit', () => nativeSubmits++);
    assert.equal(w.document.querySelector('fieldset').disabled, false);
    assert.equal(w.document.querySelector('[data-ishi-address-unavailable]').hidden, true);
    w.fetch = async (url, options) => { requests++; assert.equal(options.method, 'POST'); return response(cards, true); };
    w.document.querySelector('[data-ishi-address-save]').click(); await tick();
    assert.equal(requests, 1); assert.equal(nativeSubmits, 0); assert.ok(w.document.querySelector('.woocommerce-Addresses')); w.close();
});
test('without script initialization editor stays disabled with visible explanation', () => {
    const dom = new JSDOM(html(editor));
    assert.equal(dom.window.document.querySelector('fieldset').disabled, true);
    assert.equal(dom.window.document.querySelector('[data-ishi-address-save]').type, 'button');
    assert.equal(dom.window.document.querySelector('[data-ishi-address-unavailable]').hidden, false);
    dom.window.close();
});

test('Save works when a named control masks form.action', async () => {
    const w = setup(editor); const form = w.document.querySelector('form');
    // jsdom does not implement every browser named-property override.
    Object.defineProperty(form, 'action', { value: form.querySelector('[name="action"]') });
    let calls = 0;
    w.fetch = async (url, options) => {
        calls++; assert.equal(url, 'https://example.test/wp-json/ishi-profile/v1/addresses/billing');
        assert.equal(options.body.get('action'), 'ishi_save_customer_address');
        return response(cards, true);
    };
    form.querySelector('[data-ishi-address-save]').click(); await tick();
    assert.equal(calls, 1); assert.ok(w.document.querySelector('.woocommerce-Addresses')); w.close();
});

test('Edit is a disabled non-navigating button without initialization', () => {
    const dom = new JSDOM(html(cards));
    const edit = dom.window.document.querySelector('[data-ishi-address-edit]');
    assert.equal(edit.tagName, 'BUTTON'); assert.equal(edit.disabled, true);
    assert.equal(edit.getAttribute('href'), null); dom.window.close();
});

test('saving status clears after a failed request and allows retry', async () => {
    const w = setup(editor);
    const status = w.document.createElement('span');
    status.setAttribute('data-ishi-save-status', '');
    status.setAttribute('data-ishi-saving-text', 'Saving…');
    w.document.querySelector('form').append(status);
    let fail;
    w.fetch = () => new Promise((resolve, reject) => { fail = reject; });
    submit(w);
    assert.equal(status.textContent, 'Saving…');
    fail(new Error('Network failed'));
    await tick();
    assert.equal(status.textContent, '');
    assert.equal(w.document.querySelector('[data-ishi-address-save]').hasAttribute('aria-disabled'), false);
    assert.ok(w.document.querySelector('[role="alert"]'));
    w.fetch = async () => response(cards, true);
    submit(w); await tick();
    assert.ok(w.document.querySelector('.woocommerce-Addresses'));
    w.close();
});
