(function () {
    'use strict';
    if (!window.fetch || !window.FormData || !window.DOMParser) return;
    const selector = '.ishi-customer-addresses';
    const busy = new WeakSet();

    function samePage(url) {
        return url.origin === window.location.origin && url.pathname === window.location.pathname;
    }

    async function navigate(root, url, form, submitter) {
        if (busy.has(root)) return;
        busy.add(root);
        root.setAttribute('aria-busy', 'true');
        root.querySelectorAll('[data-ishi-navigation-error]').forEach(node => node.remove());
        const controls = Array.from(root.querySelectorAll('button, input, select, textarea'));
        const disabled = controls.map(node => node.disabled);
        // Serialize before disabling controls, including the clicked submit button.
        const body = form ? new FormData(form) : undefined;
        if (body && submitter && submitter.name) body.append(submitter.name, submitter.value);
        controls.forEach(node => { node.disabled = true; });
        try {
            const response = await fetch(url.href, {
                method: form ? 'POST' : 'GET', body,
                credentials: 'same-origin', cache: 'no-store', redirect: 'follow'
            });
            if (!response.ok || !samePage(new URL(response.url))) throw new Error('Unexpected response');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const matches = doc.querySelectorAll(selector);
            if (matches.length !== 1) throw new Error('Address interface missing');
            const next = matches[0];
            const editor = next.querySelector('form[data-ishi-address-form]');
            const cards = next.querySelector('.woocommerce-Addresses');
            if (!editor && !cards) throw new Error('Address view missing');
            // A POST may show cards only after our server's confirmed-success redirect.
            if (form && cards && (!response.redirected || !new URL(response.url).searchParams.has('ishi_address_notice'))) {
                throw new Error('Save confirmation missing');
            }
            // Do not execute scripts from the fetched full page or replace any parent widgets.
            next.querySelectorAll('script').forEach(node => node.remove());
            root.replaceChildren(...Array.from(next.childNodes));
            if (window.jQuery) {
                window.jQuery(root).find('#billing_country, #shipping_country').trigger('refresh');
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
            controls.forEach((node, index) => { node.disabled = disabled[index]; });
            root.removeAttribute('aria-busy');
            busy.delete(root);
        }
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-ishi-address-edit]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        const root = link.closest(selector);
        const url = new URL(link.href);
        if (!root || !samePage(url)) return;
        event.preventDefault();
        navigate(root, url);
    });
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.matches('form[data-ishi-address-form]')) return;
        const root = form.closest(selector);
        const url = new URL(form.action);
        if (!root || !samePage(url) || event.defaultPrevented) return;
        event.preventDefault();
        navigate(root, url, form, event.submitter);
    });
}());
