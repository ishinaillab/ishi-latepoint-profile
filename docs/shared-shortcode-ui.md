# Shared shortcode contract (1.4.0)

All plugin account shortcodes use Ishi_Shortcode_UI and assets/shortcode-ui.js.
There is one delegated frontend runtime, not a separate Profile or Addresses listener.
This contract also applies to new plugin shortcodes. It does not alter third-party shortcodes.

## Adding a component

1. Register its shortcode through Ishi_Shortcode_UI::register($tag, $render_callback).
   This automatically participates in early theme asset detection and prevents caching during rendering.
2. Keep templates in this plugin. Print Ishi_Theme_Compatibility::render_styles(), then one root
   with Ishi_Shortcode_UI::root_attributes('component-key', 'optional-component-class').
   Put its contents in a child div.woocommerce-MyAccount-content.
3. Render a display panel with data-ishi-ui-view, or a form with data-ishi-ui-form,
   data-ishi-ui-url set to the server-generated REST endpoint, method=post,
   novalidate and onsubmit="return false;". Do not add an action URL or navigation fallback.
4. Use type=button data-ishi-ui-edit data-ishi-ui-url for edit controls.
   Use type=button data-ishi-ui-save for Save. Initially disable controls with
   data-ishi-ui-control or data-ishi-ui-ready (or place fields inside a disabled
   data-ishi-ui-ready fieldset if that fits the existing markup).
   Include a data-ishi-ui-unavailable explanation, hidden when the runtime is ready.
   Do not disable security hidden inputs outside such a fieldset.
5. Register authenticated GET/POST REST routes under Ishi_Shortcode_UI::REST_NAMESPACE.
   Use Ishi_Shortcode_UI::permission as the minimum permission callback, then perform
   the component's own ownership, form nonce, allowed-field and signed-revision checks.
   Never use a browser-supplied customer/user ID for authorization.
6. Validate, save through the owning system, run the appropriate domain hooks and
   reread persisted values. Return Ishi_Shortcode_UI::response($component, $html, $saved, $view).
   saved is null for a read, false for an unsuccessful save (HTTP 422), true ONLY after
   confirmed persistence. On failure return an editor with errors and safe retained values.
   Partial writes must be described honestly; do not claim atomic transactions.
7. Never echo password values or store them in transient notices. Reject unexpected uploads.
   If native persistence renews the authenticated user's login cookie, temporarily register
   Ishi_Shortcode_UI::renewed_cookie on set_logged_in_cookie with six arguments during the
   operation, and remove it in finally, as Profile does. Generate HTML/nonces afterward.
8. Do not call My Account controllers, redirects, history/location APIs or automatic POST retries.
   The runtime replaces only the root's children. Custom field widgets can listen for the
   bubbling ishi:ui-updated event, whose detail contains component, saved and view.

## Shared behavior

- Cookie-authenticated, same-origin fetch with REST nonce, no-store and redirect:error.
- Exact response component/root validation and explicit save confirmation.
- Local duplicate-request guard; Save uses aria-disabled while the request is pending.
  Other controls are temporarily disabled, restored after completion. No "Saving..." label.
- Passwords are cleared after completed/uncertain requests; no silent retries or document navigation.
- Fresh REST nonces propagate to all plugin roots after a response. Form nonces remain bound
  to their own domain/account. A different already-open form can need review/resubmission
  if a password change renewed the session and invalidated its old form nonce.
- Accessible error/success feedback and focus without moving or resetting the parent widget.
- Scoped WooCommerce/Qwery wrappers and original stylesheet handles. Account content keeps
  width:100% and float:none. The approved Edit-button/link adaptation is shared via
  button.edit[data-ishi-ui-edit]; Save retains the theme's own button appearance.
- Registered SelectWoo/WooCommerce country and locale scripts are available to all components.
  New form fragments initialize Qwery first, then refresh native country controls.
  Other native select widgets still require their owning library's initialization.
- Opened SelectWoo/Select2 dropdowns within .ishi-theme-account use Qwery scheme_light;
  closed fields and the surrounding page keep their original scheme.

## Domain boundaries and limits

Profile remains LatePoint data plus WordPress display_name, using the previously audited native
save/password methods and synchronization checks. Addresses remains WC_Customer data with the
existing country/locale definitions, extension hooks, type allowlist and verification.

These domain rules are not interchangeable; sharing a runtime must not route address data
through LatePoint or profile data through WooCommerce. Custom fields still need a domain adapter.

Addresses supports one instance per page because native WC country/state scripts use fixed IDs.
Profile can have multiple instances; returned fragment IDs are scoped to avoid collisions.
Signed revisions detect stale forms, not truly simultaneous database races. Upstream caches
must exclude authenticated account pages and REST responses. WordPress footer scripts must run.
Third-party extensions that exit/redirect during their hooks can still abort a response; the
browser will fail safely in place and will not automatically repeat an uncertain write.

## Upgrade

Replace the entire plugin and clear cached plugin scripts/styles. Old Profile admin-post forms
are rejected without writes or redirects. Both current shortcodes use background REST saves;
legacy ishi_lp_notice and address notice-token navigation are not used.

## Optional shared card layout (1.4.1)

For paired summary cards, use .ishi-ui-card-grid around .ishi-ui-card items. Each item contains .ishi-ui-card-header with heading and Edit control, followed by .ishi-ui-card-body containing its remaining content and extension output. The grid shares three content-sized rows, allows wrapping, and stacks based on available container width. This opt-in layout is available to future shortcodes; ordinary forms keep their current layout.
