# Ishi LatePoint Profile 1.4.1

## 1.4.1 — Address heading alignment

The initial address cards use the shared opt-in `ishi-ui-card-grid` layout. CSS subgrid aligns heading, Edit-control and body rows while allowing natural text wrapping. Auto-fit columns use the component's available width, a minimum of 18em per card (capped at 100%), and the existing 4% column gap. Narrow containers stack the cards; browsers without subgrid support receive a single-column fallback. Qwery's typography, colors and margins remain in effect. No fixed heading heights, nowrap, text shrinking, navigation or save-handler changes.

The native 48% floats allowed Shipping to wrap independently of Billing. The scoped grid replaces those floats only within the opt-in card group. Extension output stays inside each card body. Tests cover unequal heading heights, narrow containers on wide pages, long labels and enlarged text, plus existing save-flow regression checks.

## 1.4.0 — Shared shortcode runtime

Both existing shortcodes now use one in-place frontend runtime and shared REST/security/theme infrastructure. Profile saves in the background too, with no notice-token redirect. Its LatePoint validation, WordPress display name, native password behavior and persistence checks remain intact. Addresses keeps its working edit/save/card flow.

Future plugin shortcodes must use the same registry, wrappers, data attributes and response contract documented in [docs/shared-shortcode-ui.md](docs/shared-shortcode-ui.md). Common functionality includes duplicate-request prevention, error feedback, safe input retention, nonce refresh after session renewal, Qwery-before-WooCommerce enhancement, and light-scheme opened dropdowns. No Saving label or select visibility override is introduced.

Profile routes: GET/POST /ishi-profile/v1/profile. REST requests use login cookies and a REST nonce, plus the existing customer-bound form nonce and signed revision for writes. Old admin-post Profile submissions are rejected without saving. Replace the complete plugin folder and clear cached plugin assets.

The existing `[ishi_latepoint_profile]` remains available. The new `[ishi_customer_addresses]` shortcode provides a standalone WooCommerce billing/shipping address book. Place it once on any normal WordPress page or server-rendered shortcode location. Customers must sign in; WooCommerce must be active. No LatePoint customer record is required for addresses.

## Installation

Replace the existing plugin with the complete updated plugin folder, including `includes/` and `templates/`. Add `[ishi_customer_addresses]` to a page. It initially shows both address cards. Edit opens the chosen address on that same page; a confirmed save restores the cards in place when JavaScript is available. Validation failures keep the editor and submitted values.

All three PHP templates reside in the plugin templates/ directory. Both shortcodes load only these bundled files; there is no child-theme lookup or override. Existing child-theme copies are ignored and do not need updating. Theme CSS still controls appearance.

Exclude the address page from full-page/CDN caching. The module sends no-cache headers and sets DONOTCACHEPAGE, but upstream caches must respect authenticated requests. Render through the normal WordPress frontend lifecycle (including footer scripts), not an independently injected shortcode fragment lacking the plugin scripts. Only one address interface per page is supported because WooCommerce locale scripts use fixed field IDs.

## Ownership and security

All address reads/writes use a fresh `WC_Customer(get_current_user_id())`. The browser cannot select a customer. Billing/shipping is strictly allowlisted. A type-bound WordPress nonce protects POST requests; a signed address revision detects edits opened before a previous change. The revision check is not a database lock against simultaneous requests.

Fields come from WooCommerce country/locale definitions and filters. Registered custom fields must use the selected `billing_` or `shipping_` prefix; unknown submitted fields are ignored. Country is required by this adapter. File uploads are unsupported. WooCommerce validators and CRUD setters/metadata are used, with additional server-side permitted-country/state checks. Both cards are displayed even when checkout is configured to ship to billing only.

Persistence is reread before success and after the native post-save hook. WooCommerce and extension writes are not an atomic transaction: a later failure can leave changes stored, and the error explains this. Trusted extensions can have their own side effects. Hooks that require `is_account_page()`, endpoint query variables, the native POST action, or checkout-only context need their own standalone integration.

## REST interaction (1.3.0)

The shortcode always starts with both address cards. Edit controls are non-navigating buttons, disabled until their JavaScript handler is ready. The script maintains the displayed view locally and replaces only the shortcode contents. It never changes the page URL or history. Both Edit and Save require JavaScript; errors keep the page in place.

Plugin routes registered on rest_api_init:
- GET /ishi-profile/v1/addresses: current customer's cards.
- GET /ishi-profile/v1/addresses/billing or /shipping: selected editor.
- POST to the selected address route: validate, persist, verify and return updated cards or field errors.

URLs are generated by rest_url(), including installations without pretty permalinks. Requests use WordPress login cookies and X-WP-Nonce for wp_rest. Permission callbacks require authentication and a valid nonce. The route's strict billing/shipping type cannot be replaced by a body parameter, and the current authenticated user is always the customer. Responses are private/no-store. The existing form nonce and signed revision checks remain additional safeguards.

The endpoint wraps the verified WooCommerce save adapter; it does not replace field definitions, validation, CRUD persistence, hooks or read-back checks. REST body data is converted into the WP-slashed context expected by the adapter, and original globals are restored after save hooks. Validation returns HTTP 422 with editor HTML; authentication and nonce errors return 401/403 and leave existing inputs intact in the browser. Success includes saved: true and freshly rendered cards. No wp_send_json(), redirect, notice-token transient, frontend request header switch or query-string view selection is used for REST responses.

No-JavaScript mode cannot edit or save; it shows an unavailable explanation. Old page POSTs are rejected without persistence. Unexpected API redirects fail safely; writes are never automatically retried. Custom field widgets can initialize after the bubbling ishi:addresses-updated event. WooCommerce country/state controls refresh after replacement. One shortcode instance per page remains supported because native field IDs are fixed.

## Verification

PHP adapter tests cover permissions, type isolation, nonces, validation, persistence and slashing. DOM tests cover background responses and failures. Chromium checks the complete Billing and Shipping Edit/Save loops and asserts only one document request with the parent container remaining open. These are automated fixtures, not a claim that every live theme or extension has been tested.

The WooCommerce source audit used 11.0.1 from the supplied backup. See docs/woocommerce-address-audit.md for native behavior. Test the upgrade on the actual site, including any address-field extensions and REST/security/cache plugins.

## Theme presentation (1.3.1)

Both shortcodes expose scoped `.woocommerce`, `.woocommerce-page`, `.woocommerce-account` and `.woocommerce-MyAccount-content` wrappers. Qwery's existing WooCommerce styles and responsive styles are loaded through its CSS loader, retaining its active skin/child-theme file resolution. Already-enqueued theme/child-theme overrides participate in the normal CSS cascade. Templates remain bundled inside this plugin.

The compatibility stylesheet removes Qwery's sidebar width reservation (width: 100%; float: none) and, as of 1.3.6, applies an explicitly approved, narrowly scoped Edit-button adaptation to match Qwery's native Edit links. Buttons keep their non-navigation semantics and a visible keyboard focus indicator. Address column widths and breakpoints come from the theme, not a duplicate plugin grid. No account body classes, endpoint routing, account JavaScript, REST saving logic, or address navigation behavior were added or changed.

This activates theme selectors that match the scoped markup; it cannot automatically reproduce arbitrary rules requiring `body.woocommerce-account`, a particular page ID, navigation sibling, or different template markup. Those require a separately verified, scoped adaptation rather than changing the surrounding page. Theme template PHP overrides are not loaded. Without Qwery, registered WooCommerce styles remain available.

## Country/state select compatibility (1.3.2)

Standalone address pages explicitly load WooCommerce SelectWoo and its select2 stylesheet. New editors are enabled before the native country refresh initializes country/state controls. The visibility fallback introduced in 1.3.2 was removed in 1.3.3 at the site owner's request; Qwery controls unenhanced select visibility. Enhancement errors do not report a confirmed save as failed. No page navigation or persistence behavior changes.

## 1.3.3

Removed only the country/state visibility override. SelectWoo assets, native refresh, address persistence and in-place navigation remain intact. No replacement visibility workaround or single-country fix is included.

## 1.3.4

Removed custom Edit-button typography, colors, hover/focus styling, breakpoint, clearing pseudo-element, and the address fieldset's inline presentation reset. Only account content width: 100% and float: none remain as plugin CSS overrides. Existing WooCommerce/Qwery stylesheets and styling wrappers remain. No change to select initialization, request handling or no-navigation behavior; theme rules requiring different markup are not recreated by custom CSS.

## 1.3.5

After inserting an address editor and refreshing WooCommerce country/state controls, notify Qwery through its action.init_hidden_elements event with only the shortcode root. This lets Qwery decorate the single-country select using its own wrapper and CSS. The parent panel is not passed to the initializer. No visibility override or other CSS was added; confirmed saving remains independent of theme initialization errors.

## 1.3.6

Adapt only the address-card Edit buttons to Qwery's Edit-link appearance using its configured font and color variables and corresponding responsive typography. Save buttons, select visibility, fieldsets, Qwery initialization, and the REST interaction are unchanged. No clearing pseudo-element or select visibility override is restored.

## 1.3.7

Keep address-card Edit controls link-styled during loading. Narrowly scoped disabled color rules override Qwery's important disabled-button background/text colors; actual disabled behavior and keyboard focus styling remain unchanged. Save buttons are unaffected.

## 1.3.8

Save changes uses aria-disabled while saving rather than the native disabled attribute, preserving normal theme styling. The existing busy guard rejects duplicate pointer/keyboard submissions, fields remain locked, and the previous ARIA state is restored on errors. No Save-button CSS overrides are added.

## 1.3.10

Completely remove the Saving status introduced in 1.3.9 and restore the prior address submission behavior. The status markup, translated text, JavaScript updates and status-specific tests are removed. Existing aria-disabled/busy protection, Qwery styling and no-navigation flow remain unchanged.

## 1.3.11

Run Qwery's scoped fragment initialization before WooCommerce refresh enhances the new selects. This allows Qwery's select_container wrapper to supply the arrow that its CSS removes from SelectWoo. No CSS overrides are introduced. Verified with the available Qwery/WooCommerce source in a browser reproduction; exact authenticated live dropdown style parity remains unverified.

## 1.3.12

With Qwery active, enqueue WooCommerce's registered select2 base stylesheet at wp_enqueue_scripts priority 20, after WooCommerce registration (10) and before Qwery skin styles (1000+). This intentionally loads that one base stylesheet on Qwery frontend pages even when no shortcode is detectable, covering builders that render the shortcode only after wp_head. WordPress's existing handle prevents repeated printing during shortcode rendering. No global SelectWoo JavaScript, copied theme CSS, new color declarations, or changes to address processing are introduced. Cache/optimization layers must preserve stylesheet order; live authenticated parity still requires site verification.

## 1.3.13

Opened SelectWoo/Select2 dropdowns belonging to any plugin shortcode's shared .ishi-theme-account wrapper receive Qwery's scheme_light class. A delegated select2:open listener uses the source select's own instance; unrelated dropdowns and the closed fields are untouched. Any previous dropdown scheme classes are restored on close. The same integration covers future shortcodes using that wrapper and REST-replaced editors. Qwery's existing variables supply all colors; there is no CSS override, dropdown relocation, or native-select enhancement. Browser-native select popups are unchanged.
