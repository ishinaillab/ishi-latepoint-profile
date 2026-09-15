# Ishi LatePoint Profile 1.2.3

The existing `[ishi_latepoint_profile]` remains available. The new `[ishi_customer_addresses]` shortcode provides a standalone WooCommerce billing/shipping address book. Place it once on any normal WordPress page or server-rendered shortcode location. Customers must sign in; WooCommerce must be active. No LatePoint customer record is required for addresses.

## Installation

Replace the existing plugin with the complete updated plugin folder, including `includes/` and `templates/`. Add `[ishi_customer_addresses]` to a page. It initially shows both address cards. Edit opens the chosen address on that same page; a confirmed save restores the cards in place when JavaScript is available. Validation failures keep the editor and submitted values.

All three PHP templates reside in the plugin templates/ directory. Both shortcodes load only these bundled files; there is no child-theme lookup or override. Existing child-theme copies are ignored and do not need updating. Theme CSS still controls appearance.

Exclude the address page from full-page/CDN caching. The module sends no-cache headers and sets DONOTCACHEPAGE, but upstream caches must respect authenticated requests. Render through the normal WordPress frontend lifecycle (including footer scripts), not an independently injected shortcode fragment lacking the plugin scripts. Only one address interface per page is supported because WooCommerce locale scripts use fixed field IDs.

## Ownership and security

All address reads/writes use a fresh `WC_Customer(get_current_user_id())`. The browser cannot select a customer. Billing/shipping is strictly allowlisted. A type-bound WordPress nonce protects POST requests; a signed address revision detects edits opened before a previous change. The revision check is not a database lock against simultaneous requests.

Fields come from WooCommerce country/locale definitions and filters. Registered custom fields must use the selected `billing_` or `shipping_` prefix; unknown submitted fields are ignored. Country is required by this adapter. File uploads are unsupported. WooCommerce validators and CRUD setters/metadata are used, with additional server-side permitted-country/state checks. Both cards are displayed even when checkout is configured to ship to billing only.

Persistence is reread before success and after the native post-save hook. WooCommerce and extension writes are not an atomic transaction: a later failure can leave changes stored, and the error explains this. Trusted extensions can have their own side effects. Hooks that require `is_account_page()`, endpoint query variables, the native POST action, or checkout-only context need their own standalone integration.

## Verification

### Current routing behavior

The Cancel link is absent. Submissions run at `wp_loaded` priority 5, before native WC form processing and frontend `template_redirect` handlers. After confirmed persistence, the module returns the address display as JSON and the browser replaces only the shortcode contents.

As of 1.1.2, all templates are loaded exclusively from the plugin. Template version headers no longer control loading.

The temporary redirect guard has been removed after the site owner identified and resolved the redirect causes. The module does not intercept redirects from third-party callbacks. Address-save hooks remain available for extension compatibility; callbacks must be compatible with the standalone workflow.

Source audit: WooCommerce **11.0.1** from the supplied site backup, not a live installation. The live version remains unconfirmed. See `docs/woocommerce-address-audit.md`. GitHub Actions lints PHP and runs `php tests/address-adapter-test.php`; those tests use API doubles and do not replace a real WordPress/WooCommerce browser test.

Before live use, verify billing and shipping saves, country/state changes, validation failures, theme appearance, and any installed address-field extensions on staging.

## In-place address navigation (1.2.3)

The navigation script loads in the document head without WooCommerce dependencies, including on pages whose builder stores the shortcode outside post_content. A capture-phase listener handles this shortcode's Edit and Save events before parent widgets can stop their propagation.

Background requests send X-Ishi-Address-Request: 1 to the same page. GET renders the authenticated customer's selected editor. POST uses the existing nonce, ownership checks, validation, WC_Customer save, hooks and read-back verification. After that result, PHP returns JSON containing the rendered cards on success or editor/errors on failure. Background saves do not generate ishi_address_notice tokens or redirects. The header selects transport, not authorization; all save security checks still apply.

Only the shortcode's children are replaced. Parent nodes, tabs, accordions, URL and history remain untouched. Unexpected redirects are rejected by fetch, not intercepted through a PHP redirect filter. Network/response failures retain entered values and are never automatically retried. Custom field widgets can listen for ishi:addresses-updated; fetched inline scripts are not executed. Native country/state controls are refreshed if their scripts are available.

Address saves now require the background transport. Save is a non-submitting button, and the form fields remain disabled with a visible explanation until the navigation script initializes. Enter-key submissions are intercepted as well. Ordinary POSTs are rejected before any persistence; there is no address success redirect or notice-token generation. JavaScript-disabled or blocked browsers cannot save addresses through this interface.

If the loading/unavailable message remains, check that assets/address-navigation.js?ver=1.2.3 is served and exclude it from script-delay rules. This failure leaves the current page in place rather than submitting normally. A page-level request explicitly forced by unrelated third-party code cannot be prevented by this form; the server still rejects a non-background save before writing.

### Save button URL correction

The hidden input named action can mask the browser's form.action property. Version 1.2.3 reads the action attribute explicitly in both Save-click and submit handlers. The field still sends the required PHP action unchanged. Regression coverage includes the named control and a real Chromium browser test.
