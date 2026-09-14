# Native address workflow and adapter decisions

## Source and scope

Inspected the actual WooCommerce directory in `Downloads/u328762438.povnailstudio-com.20260830013744/domains/povnailstudio.com/public_html/wp-content/plugins/woocommerce`. Its plugin header identifies version **11.0.1**. Line numbers below refer to that backup, not a moving upstream branch. The current live-site version was not available. The supplied custom templates carry WooCommerce template version 9.3.0; template version is not the installed plugin version.

## Native trace

| Source relative to WooCommerce | Verified behavior |
| --- | --- |
| `includes/shortcodes/class-wc-shortcode-my-account.php:178` `WC_Shortcode_My_Account::edit_address()` | Prepares selected address, country and fields; country falls back to base/permitted countries. At 203 calls `get_address_fields()`, at 206–207 enqueues `wc-country-select` and `wc-address-i18n`; 216–218 supplies user-email fallback; 223 applies the field-value filter; 230 applies `woocommerce_address_to_edit`. Native preparation reads user metadata, whereas the adapter reads the customer CRUD model. |
| `includes/class-wc-form-handler.php:147` `WC_Form_Handler::save_address()` | Requires logged-in user, POST action `edit_address`, nonce field `woocommerce-edit-address-nonce` (or `_wpnonce`) for `woocommerce-edit_address`. Constructs current user's WC_Customer. At 174 derives type from `$wp->query_vars['edit-address']`; this dependency is removed. |
| Same file:180–250 | Gets fields for submitted country; checkbox presence becomes integer, other values use `wc_clean(wp_unslash(...))`; applies `woocommerce_process_myaccount_field_{$key}`; checks required fields, postcode, phone and email. Uses callable `set_{$key}` or `update_meta_data($key,$value)` for custom fields. Catches WC_Data_Exception. |
| Same file:270–298 | Calls validation hook with user ID, address type, field definitions and customer; checks error notices; saves; calls post-save hook with the same four arguments; checks errors again; adds success notice and redirects to My Account. The adapter replaces this redirect and request action, preserving the two business hooks. |
| `includes/class-wc-countries.php:758`, `:864`, `:896`, `:1746–1812` `WC_Countries` | Default address fields, default-field filter, locale definitions and overlays; billing phone/email settings; `woocommerce_billing_fields` or `woocommerce_shipping_fields`; priority sorting. No hard-coded field list is used in the adapter. |
| `includes/class-wc-countries.php:231` `get_states()` | Country-specific state map used for extra server-side state membership checks. Native account save does not itself enforce this membership. Known state names/codes are canonicalized; countries with an empty state list clear state; countries allowing free text retain it. |
| `includes/class-wc-customer.php:101–128` | WC_Customer constructed with user ID uses persistent customer data; session mode is a separate constructor option. The adapter does not use the session-backed global customer as its data owner. |
| Same file:554 and address getters/setters; `:1131–1144` | Address getters expose CRUD values; billing email setter validates/sanitizes email, phone setter sets the property. Billing email is address data, not a request to change the WP login email. |
| `includes/abstracts/abstract-wc-data.php:284–311` `save()` | Invokes object-save hooks and data-store create/update; returns object ID. An ID alone is insufficient proof that all metadata writes succeeded. |
| `includes/data-stores/class-wc-customer-data-store.php:198–227`, `:260–340` | Persists changed customer/address properties and custom metadata; invokes `woocommerce_update_customer` and `woocommerce_customer_object_updated_props`. WC internally also updates existing WP account attributes as part of its customer store; the adapter does not intentionally change root email/display name. |
| `includes/wc-account-functions.php:379–395` `wc_get_account_formatted_address()` | Constructs explicit customer's WC_Customer, obtains chosen address, removes phone/email, applies `woocommerce_my_account_my_address_formatted_address`, calls country formatter. Safe outside My Account despite its name. |
| `includes/class-wc-countries.php:596–687` | Country address formats, formatting/replacement filters and formatted-address output. Empty formatting receives the original template's empty-address message. |
| `includes/wc-template-functions.php:3327` `woocommerce_form_field()` | Native field rendering retained, including its field filters and country/state controls. |
| `includes/wc-notice-functions.php:114`, `:241–260` | Notices reside in WC session. The adapter initializes a session if necessary, temporarily isolates operation notices, and restores unrelated notices in finally. |
| `assets/js/frontend/address-i18n.js:112` | Recognizes `.woocommerce-address-fields__field-wrapper`, retained by the template; native country/state and locale scripts are enqueued. |

## Extension points retained

Field generation preserves `woocommerce_default_address_fields`, locale filters, `woocommerce_billing_fields`, `woocommerce_shipping_fields`, and priority sorting through `get_address_fields()`. Render values retain `woocommerce_my_account_edit_address_field_value`. The adapter applies `woocommerce_address_to_edit` to both rendering and saving so added fields are validated consistently; native uses that filter only during rendering.

Submission retains `woocommerce_process_myaccount_field_{$key}`, `woocommerce_after_save_address_validation($user_id,$address_type,$address,$customer)` and `woocommerce_customer_save_address($user_id,$address_type,$address,$customer)`. The latter gained its additional arguments in WC 9.8. Normal WC CRUD object/data-store hooks execute through `save()`.

Presentation retains the supplied description/title filters, `woocommerce_my_account_after_my_address`, `woocommerce_before_edit_account_address_form`, `woocommerce_after_edit_account_address_form`, and the type-specific before/after edit-address form hooks. Formatting uses the native formatted-address filter and WC country formatter. The get-addresses filter can customize the two card titles, but cannot remove a required card or introduce an arbitrary address type.

## Standalone implementation

`Ishi_WooCommerce_Addresses::render()` explicitly prepares all template variables. `handle_request()` runs on template_redirect with unique POST action `ishi_save_customer_address`; `save_submission()` separates validation/persistence from presentation. Query parameter `ishi_address` selects only billing/shipping. No account endpoint, account URL, `$wp` endpoint state, native nonce/action or automatic WC account handler is used.

The nonce action is `ishi_save_customer_address_{billing|shipping}`, field `ishi_address_nonce`. Identity is always server-derived. Only native/filter-defined fields in the selected namespace can become setters or metadata. The adapter blocks validation callbacks from changing customer identity or unrelated core properties. It does not sandbox trusted PHP hooks.

Successful saves reread all intended field values from a fresh customer, fire the native post-save hook, and verify again. A 303 redirect removes edit mode and uses an opaque, session-bound, expiring success-notice token. Failed saves render their errors and entered values without redirecting. No address values are stored in URLs or temporary notice storage. Authentication/nonce/stale-form rejection intentionally reloads current data rather than retaining untrusted or outdated values.

## Limits to verify in the real installation

The tests exercise adapter behavior with WP/WC doubles. A live database, browser, theme styling, exact third-party extensions, and live plugin version have not been verified. The adapter's prefix/country/no-upload rules are deliberate boundaries for standalone custom fields. Hooks conditional on My Account routing will not run their conditional functionality here. WooCommerce provides no transaction across all customer metadata and arbitrary hook side effects; a partial write remains possible and never receives an unconditional success message.
