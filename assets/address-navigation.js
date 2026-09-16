(function () {
    'use strict';
    if (!window.fetch || !window.FormData || !window.DOMParser) return;
    const selector = '.ishi-customer-addresses';
    const busy = new WeakSet();

    function enable(root) {
        if (busy.has(root)) return;
        root.querySelectorAll('[data-ishi-address-control]').forEach(node => { node.disabled = false; });
        root.querySelectorAll('[data-ishi-address-ready]').forEach(node => { node.disabled = false; });
        root.querySelectorAll('[data-ishi-address-unavailable]').forEach(node => { node.hidden = true; });
    }
    function initialize() { document.querySelectorAll(selector).forEach(enable); }
    document.addEventListener('DOMContentLoaded', initialize);
    new MutationObserver(initialize).observe(document.documentElement, { childList: true, subtree: true });
    initialize();

    function sameOrigin(url) {
        return url.origin === window.location.origin;
    }

    async function navigate(root, url, form, submitter) {
        if (busy.has(root)) return;
        busy.add(root);
        root.setAttribute('aria-busy', 'true');
        root.querySelectorAll('[data-ishi-navigation-error]').forEach(node => node.remove());
        const controls = Array.from(root.querySelectorAll('button, input, select, textarea'));
        const disabled = controls.map(node => node.disabled);
        const ariaDisabled = controls.map(node => node.getAttribute('aria-disabled'));
        // Serialize before disabling controls, including the clicked submit button.
        const body = form ? new FormData(form) : undefined;
        if (body && submitter && submitter.name) body.append(submitter.name, submitter.value);
        controls.forEach(node => {
            // Keep Save's native theme appearance. The busy guard blocks both
            // pointer and keyboard resubmissions; ARIA communicates unavailability.
            if (node.matches('[data-ishi-address-save]')) node.setAttribute('aria-disabled', 'true');
            else node.disabled = true;
        });
        try {
            const response = await fetch(url.href, {
                method: form ? 'POST' : 'GET', body,
                credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                headers: { 'X-WP-Nonce': root.getAttribute('data-ishi-rest-nonce'), 'Accept': 'application/json' }
            });
            if ((!response.ok && response.status !== 422) || new URL(response.url).href !== url.href) throw new Error('Unexpected response');
            const result = await response.json();
            if (result.ishi_addresses !== true || typeof result.html !== 'string') throw new Error('Invalid response');
            const doc = new DOMParser().parseFromString(result.html, 'text/html');
            const matches = doc.querySelectorAll(selector);
            if (matches.length !== 1) throw new Error('Address interface missing');
            const next = matches[0];
            const editor = next.querySelector('form[data-ishi-address-form]');
            const cards = next.querySelector('.woocommerce-Addresses');
            if (!editor && !cards) throw new Error('Address view missing');
            // A POST may show cards only after our server's explicit persistence confirmation.
            if (form && cards && result.saved !== true) {
                throw new Error('Save confirmation missing');
            }
            // Do not execute scripts from the fetched full page or replace any parent widgets.
            next.querySelectorAll('script').forEach(node => node.remove());
            root.setAttribute('data-ishi-rest-nonce', next.getAttribute('data-ishi-rest-nonce') || root.getAttribute('data-ishi-rest-nonce'));
            root.replaceChildren(...Array.from(next.childNodes));
            // Enable the new editor before WooCommerce enhances its selects.
            // enable() is intentionally blocked while this request is busy.
            root.querySelectorAll('[data-ishi-address-ready]').forEach(node => { node.disabled = false; });
            if (editor && window.jQuery && window.QWERY_STORAGE) {
                try {
                    // Qwery's normal fragment initializer decorates raw selects,
                    // before SelectWoo enhances them and makes Qwery skip them.
                    // Pass only this shortcode: never reinitialize the parent panel.
                    window.jQuery(document).trigger('action.init_hidden_elements', [window.jQuery(root)]);
                } catch (error) {
                    console.warn('Ishi address theme initialization unavailable.', error);
                }
            }
            if (window.jQuery) {
                try {
                    // WC refresh rebuilds states and fires country_to_state_changed,
                    // which initializes SelectWoo using WC's labels and configuration.
                    window.jQuery(root).find('#billing_country, #shipping_country').trigger('refresh');
                } catch (error) {
                    // Presentation enhancement must not turn a confirmed save into
                    // a save error. Select visibility remains controlled by the theme.
                    console.warn('Ishi address select enhancement unavailable.', error);
                }
            }
            root.dispatchEvent(new CustomEvent('ishi:addresses-updated', { bubbles: true }));
            const focus = root.querySelector('[role="alert"], [role="status"], h2');
            if (focus) { focus.setAttribute('tabindex', '-1'); focus.focus({ preventScroll: true }); }
        } catch (error) {
            const notice = document.createElement('div');
            notice.className = 'woocommerce-error';
            notice.dataset.ishiNavigationError = 'true';
            notice.setAttribute('role', 'alert');
            notice.textContent = form
                ? 'The response could not be loaded. Your address may have been saved. Your entries are still here; reload to check the stored address before submitting again.'
                : 'The address form could not be loaded. Please try again.';
            root.prepend(notice);
            // Never automatically resubmit a POST or navigate the whole page after an uncertain save.
        } finally {
            controls.forEach((node, index) => {
                node.disabled = disabled[index];
                if (node.matches('[data-ishi-address-save]')) {
                    if (ariaDisabled[index] === null) node.removeAttribute('aria-disabled');
                    else node.setAttribute('aria-disabled', ariaDisabled[index]);
                }
            });
            root.removeAttribute('aria-busy');
            busy.delete(root);
            enable(root);
        }
    }

    function requestFrom(control, form) {
        const root = control.closest(selector);
        if (!root) return;
        try {
            const url = new URL(control.getAttribute('data-ishi-address-url') || (form && form.getAttribute('data-ishi-address-url')), document.baseURI);
            if (!sameOrigin(url)) throw new Error('Invalid endpoint');
            navigate(root, url, form, form ? control : undefined);
        } catch (error) {
            const notice = document.createElement('div');
            notice.className = 'woocommerce-error';
            notice.setAttribute('role', 'alert');
            notice.textContent = 'Address editing is unavailable. Please contact support.';
            root.prepend(notice);
        }
    }
    window.addEventListener('click', function (event) {
        const button = event.target.closest('[data-ishi-address-save], [data-ishi-address-edit]');
        if (!button) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        requestFrom(button, button.hasAttribute('data-ishi-address-save') ? button.closest('form[data-ishi-address-form]') : null);
    }, true);
    window.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.matches('form[data-ishi-address-form]')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        requestFrom(form, form);
    }, true);
}());
