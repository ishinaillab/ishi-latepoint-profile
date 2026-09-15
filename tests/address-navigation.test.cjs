const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const script = fs.readFileSync(require('node:path').join(__dirname, '../assets/address-navigation.js'), 'utf8');
const cards = '<div class="woocommerce-Addresses"><h2>Addresses</h2><a data-ishi-address-edit href="/dashboard/?ishi_address=billing">Edit</a></div>';
const editor = '<form data-ishi-address-form action="https://example.test/dashboard/?ishi_address=billing"><h2>Billing</h2><input name="billing_city" value="Makati"><input name="ishi_address_nonce" value="nonce"><button name="ishi_save_address" value="billing">Save</button></form>';
const html = inner => '<div class="ishi-customer-addresses">' + inner + '</div>';
function setup(inner = cards) {
    const dom = new JSDOM('<button aria-selected="true">Third tab</button><details open><summary>Addresses</summary><section id="third">' + html(inner) + '</section></details>', { url: 'https://example.test/dashboard/#third', runScripts: 'outside-only' });
    const w = dom.window;
    w.fetch = async () => { throw Error('No response'); };
    w.eval(script);
    return w;
}
const tick = () => new Promise(resolve => setTimeout(resolve, 20));
function response(inner, saving = false) { return { ok: true, redirected: saving, url: 'https://example.test/dashboard/' + (saving ? '?ishi_address_notice=token' : '?ishi_address=billing'), text: async () => html(inner) }; }
function submit(w) { w.document.querySelector('form').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true })); }
test('edit and confirmed save replace only shortcode contents inside an open container', async () => {
    const w = setup(); const parent = w.document.querySelector('#third'); const root = parent.firstChild; const originalURL = w.location.href;
    w.fetch = async () => response(editor);
    w.document.querySelector('a').click(); await tick();
    assert.ok(root.querySelector('form'));
    w.fetch = async (url, options) => { assert.equal(options.method, 'POST'); assert.equal(options.body.get('ishi_address_nonce'), 'nonce'); assert.equal(options.body.get('billing_city'), 'Makati'); return response(cards, true); };
    submit(w); await tick();
    assert.equal(parent.firstChild, root); assert.ok(root.querySelector('.woocommerce-Addresses'));
    assert.ok(w.document.querySelector('details').open); assert.equal(w.document.querySelector('button').getAttribute('aria-selected'), 'true'); assert.equal(w.location.href, originalURL);
    w.close();
});
test('validation failure keeps edit form and server-returned values', async () => {
    const w = setup(editor); w.fetch = async () => response('<div role="alert">Required</div>' + editor.replace('Makati', 'Retained'));
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
    w.document.querySelector('a').click(); await tick(); assert.equal(w.document.querySelector('script'), null); assert.equal(w.unwanted, undefined); w.close();
});
