<?php
/** Isolated controller tests; doubles do not replace testing against a real WooCommerce store. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
function __( $s, $d = '' ) { return $s; }
function esc_html__( $s, $d = '' ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html_e( $s, $d = '' ) { echo esc_html__( $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function wp_kses_post( $s ) { return strip_tags( (string) $s, '<strong><br><em>' ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function add_shortcode( ...$a ) {}
function add_action( ...$a ) { $GLOBALS['registrations'][] = $a; }
function add_filter( $tag, $callback, $priority = 10 ) { $GLOBALS['filters'][ $tag ] = $callback; }
function remove_filter( $tag, $callback, $priority = 10 ) { unset( $GLOBALS['filters'][ $tag ] ); }
function did_action( $s ) { return 1; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return (object) [ 'user_email' => 'login@example.test' ]; }
function wp_hash( $s ) { return hash_hmac( 'sha256', $s, 'test-secret' ); }
function wp_json_encode( $s ) { return json_encode( $s ); }
function wp_create_nonce( $a ) { return wp_hash( $a . ':' . get_current_user_id() ); }
function wp_verify_nonce( $n, $a ) { return hash_equals( wp_create_nonce( $a ), $n ); }
function wp_nonce_field( $a, $name, $ref = true ) { echo '<input name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $a ) ) . '">'; }
function wp_unslash( $s ) { return is_array( $s ) ? array_map( 'wp_unslash', $s ) : stripslashes( $s ); }
function wc_clean( $s ) { return is_array( $s ) ? array_map( 'wc_clean', $s ) : trim( strip_tags( (string) $s ) ); }
function is_email( $s ) { return filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function wc_format_postcode( $s, $country ) { return strtoupper( trim( $s ) ); }
function apply_filters( $tag, $value, ...$args ) {
    return isset( $GLOBALS['filters'][ $tag ] ) ? $GLOBALS['filters'][ $tag ]( $value, ...$args ) : $value;
}
function do_action( $tag, ...$args ) {
    $GLOBALS['events'][] = [ $tag, $args ];
    if ( isset( $GLOBALS['actions'][ $tag ] ) ) { $GLOBALS['actions'][ $tag ]( ...$args ); }
}
function wc_add_notice( $message, $type = 'success', $data = [] ) { $GLOBALS['notices'][ $type ][] = [ 'notice' => $message, 'data' => $data ]; }
function wc_get_notices( $type = '' ) { return $type ? ( $GLOBALS['notices'][ $type ] ?? [] ) : $GLOBALS['notices']; }
function wc_set_notices( $n ) { $GLOBALS['notices'] = $n; }
function wc_notice_count( $type ) { return count( wc_get_notices( $type ) ); }
function clean_user_cache( $id ) {}
function wp_cache_delete( $id, $group ) {}
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function wp_parse_url( $u ) { return parse_url( $u ); }
function remove_query_arg( $keys, $url ) {
    $parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $q );
    foreach ( (array) $keys as $k ) { unset( $q[ $k ] ); }
    return $parts['scheme'] . '://' . $parts['host'] . ( $parts['path'] ?? '/' ) . ( $q ? '?' . http_build_query( $q ) : '' );
}
function add_query_arg( $key, $value, $url ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . urlencode( $key ) . '=' . urlencode( $value ); }
function get_post() { return (object) [ 'post_content' => '[ishi_customer_addresses]' ]; }
function has_shortcode( $p, $s ) { return strpos( $p, '[' . $s . ']' ) !== false; }
function nocache_headers() {}
function get_stylesheet_directory() { return __DIR__ . '/nonexistent-child'; }
function plugins_url( $path, $file ) { return 'https://example.test/plugins/ishi-latepoint-profile/' . $path; }
function rest_url( $path ) { return 'https://example.test/wp-json/' . $path; }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : addslashes( (string) $v ); }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $route ] = $args; }
class WP_Error {
    public $code, $message, $data;
    public function __construct( $code, $message, $data ) { $this->code = $code; $this->message = $message; $this->data = $data; }
}
class WP_REST_Response {
    public $data, $status, $headers = [];
    public function __construct( $data, $status ) { $this->data = $data; $this->status = $status; }
    public function header( $key, $value ) { $this->headers[$key] = $value; }
}
class AddressRequest {
    public $type, $body, $nonce, $files = [];
    public function __construct( $type = '', $body = [] ) { $this->type = $type; $this->body = $body; $this->nonce = wp_create_nonce( 'wp_rest' ); }
    public function get_header( $key ) { return $this->nonce; }
    public function get_url_params() { return [ 'type' => $this->type ]; }
    public function get_body_params() { return $this->body; }
    public function get_file_params() { return $this->files; }
}
function select_view( $type ) {
    $p = new ReflectionProperty( Ishi_WooCommerce_Addresses::class, 'view' );
    $p->setAccessible( true ); $p->setValue( null, $type );
}
function wp_enqueue_script( $h ) { $GLOBALS['scripts'][] = $h; }
function wp_style_is( ...$a ) { return false; }
function wp_enqueue_style( $h ) {}
function wp_print_styles( $handles ) {}
function wc_wp_theme_get_element_class_name( $n ) { return 'wp-element-button'; }
function woocommerce_form_field( $key, $field, $value ) { echo '<input name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
function wc_get_account_formatted_address( $type, $id ) { return esc_html( $GLOBALS['db'][ $id ][ $type ]['first_name'] ?? '' ); }
function wp_get_session_token() { return 'session'; }
function wp_generate_uuid4() { return '11111111-1111-4111-8111-111111111111'; }
function set_transient( $k, $v, $ttl ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); }
class JsonSignal extends Exception {
    public $data, $status;
    public function __construct( $data, $status ) { $this->data = $data; $this->status = $status; }
}
function wp_send_json( $data, $status = 200 ) { throw new JsonSignal( $data, $status ); }
class RedirectSignal extends Exception { public $url, $status; public function __construct( $url, $status ) { $this->url = $url; $this->status = $status; } }
function wp_safe_redirect( $url, $status = 302 ) {
    $url = apply_filters( 'wp_redirect', $url, $status );
    $GLOBALS['redirects'][] = [ 'url' => $url, 'status' => $status ];
    // Only the top-level handler test interrupts execution to avoid its explicit exit.
    // Save callbacks use WordPress-like return behavior, not a catchable fake failure.
    if ( ! empty( $GLOBALS['interrupt_redirect'] ) ) { throw new RedirectSignal( $url, $status ); }
    return true;
}

class WC_Data_Exception extends Exception {}
class WC_Validation {
    public static function is_postcode( $v, $c ) { return (bool) preg_match( $c === 'US' ? '/^\d{5}$/' : '/^\d{4}$/', $v ); }
    public static function is_phone( $v, $c ) { return (bool) preg_match( '/^[+\d ()-]{7,}$/', $v ); }
}
class TestCountries {
    public function get_allowed_countries() { return [ 'US' => 'United States', 'PH' => 'Philippines' ]; }
    public function get_shipping_countries() { return [ 'PH' => 'Philippines' ]; }
    public function get_base_country() { return 'PH'; }
    public function get_states( $country ) { return $country === 'US' ? [ 'CA' => 'California', 'NY' => 'New York' ] : [ '00' => 'Metro Manila' ]; }
    public function get_address_fields( $country, $prefix ) {
        $fields = [];
        foreach ( [ 'first_name', 'country', 'state', 'postcode', 'city' ] as $key ) { $fields[ $prefix . $key ] = [ 'label' => $key, 'required' => true ]; }
        $fields[ $prefix . 'postcode' ]['validate'] = [ 'postcode' ];
        if ( $prefix === 'billing_' ) {
            $fields['billing_email'] = [ 'label' => 'Email', 'required' => true, 'validate' => [ 'email' ] ];
            $fields['billing_phone'] = [ 'label' => 'Phone', 'validate' => [ 'phone' ] ];
        }
        return apply_filters( 'woocommerce_' . $prefix . 'fields', $fields, $country );
    }
}
class TestWC {
    public $countries, $session;
    public function __construct() { $this->countries = new TestCountries(); $this->session = new stdClass(); }
    public function initialize_session() { $this->session = new stdClass(); }
}
function WC() { static $wc; return $wc ?: ( $wc = new TestWC() ); }
class TestMeta {
    private $data;
    public function __construct( $k, $v ) { $this->data = [ 'key' => $k, 'value' => $v ]; }
    public function get_data() { return $this->data; }
}
class WC_Customer {
    private $id, $data, $changes = [];
    public function __construct( $id ) { $this->id = isset( $GLOBALS['db'][ $id ] ) ? $id : 0; $this->data = $GLOBALS['db'][ $id ] ?? []; }
    public function get_id() { return $this->id; }
    public function set_id( $id ) { $this->id = $id; }
    public function get_billing( $c = '' ) { return $this->data['billing']; }
    public function get_shipping( $c = '' ) { return $this->data['shipping']; }
    public function get_meta( $k, $single = true, $c = '' ) { return $this->data['meta'][ $k ] ?? ''; }
    public function update_meta_data( $k, $v ) { $this->data['meta'][ $k ] = $v; }
    public function get_meta_data() { $m = []; foreach ( $this->data['meta'] as $k => $v ) { $m[] = new TestMeta( $k, $v ); } return $m; }
    public function read_meta_data( $force ) {}
    public function get_changes() { return $this->changes; }
    private function set( $t, $k, $v ) { $this->data[ $t ][ $k ] = $v; $this->changes[ $t ][ $k ] = $v; }
    public function get_billing_country( $c = '' ) { return $this->data['billing']['country']; }
    public function get_shipping_country( $c = '' ) { return $this->data['shipping']['country']; }
    public function get_billing_first_name( $c = '' ) { return $this->data['billing']['first_name']; }
    public function get_shipping_first_name( $c = '' ) { return $this->data['shipping']['first_name']; }
    public function get_billing_state( $c = '' ) { return $this->data['billing']['state']; }
    public function get_shipping_state( $c = '' ) { return $this->data['shipping']['state']; }
    public function get_billing_postcode( $c = '' ) { return $this->data['billing']['postcode']; }
    public function get_shipping_postcode( $c = '' ) { return $this->data['shipping']['postcode']; }
    public function get_billing_city( $c = '' ) { return $this->data['billing']['city']; }
    public function get_shipping_city( $c = '' ) { return $this->data['shipping']['city']; }
    public function get_billing_email( $c = '' ) { return $this->data['billing']['email']; }
    public function get_billing_phone( $c = '' ) { return $this->data['billing']['phone']; }
    public function set_billing_country( $v ) { $this->set( 'billing', 'country', $v ); }
    public function set_shipping_country( $v ) { $this->set( 'shipping', 'country', $v ); }
    public function set_billing_first_name( $v ) { $this->set( 'billing', 'first_name', $v ); }
    public function set_shipping_first_name( $v ) { $this->set( 'shipping', 'first_name', $v ); }
    public function set_billing_state( $v ) { $this->set( 'billing', 'state', $v ); }
    public function set_shipping_state( $v ) { $this->set( 'shipping', 'state', $v ); }
    public function set_billing_postcode( $v ) { $this->set( 'billing', 'postcode', $v ); }
    public function set_shipping_postcode( $v ) { $this->set( 'shipping', 'postcode', $v ); }
    public function set_billing_city( $v ) { $this->set( 'billing', 'city', $v ); }
    public function set_shipping_city( $v ) { $this->set( 'shipping', 'city', $v ); }
    public function set_billing_email( $v ) { if ( ! is_email( $v ) ) { throw new WC_Data_Exception( 'Bad email' ); } $this->set( 'billing', 'email', $v ); }
    public function set_billing_phone( $v ) { $this->set( 'billing', 'phone', $v ); }
    public function save() {
        ++$GLOBALS['writes'];
        if ( $GLOBALS['fail_save'] ) { return 0; }
        if ( ! $GLOBALS['silent_failure'] ) { $GLOBALS['db'][ $this->id ] = $this->data; }
        return $this->id;
    }
}

require dirname( __DIR__ ) . '/includes/class-ishi-woocommerce-addresses.php';
function reset_state() {
    foreach ( [ 'response' => null, 'rendered' => false, 'view' => '' ] as $key => $value ) {
        $p = new ReflectionProperty( Ishi_WooCommerce_Addresses::class, $key ); $p->setAccessible( true ); $p->setValue( null, $value );
    }
}
function fixture() {
    reset_state();
    unset( $_SERVER['HTTP_X_ISHI_ADDRESS_REQUEST'] );
    $GLOBALS['redirects'] = []; $GLOBALS['interrupt_redirect'] = false;
    $GLOBALS['uid'] = 5; $GLOBALS['writes'] = 0; $GLOBALS['fail_save'] = false; $GLOBALS['silent_failure'] = false;
    foreach ( [ 'filters', 'actions', 'events', 'notices', 'scripts', 'transients' ] as $k ) { $GLOBALS[ $k ] = []; }
    $address = [ 'first_name' => 'Existing', 'country' => 'PH', 'state' => '00', 'postcode' => '1200', 'city' => 'Makati', 'email' => 'billing@example.test', 'phone' => '+639171234567' ];
    $GLOBALS['db'] = [ 5 => [ 'billing' => $address, 'shipping' => $address, 'meta' => [] ], 9 => [ 'billing' => $address, 'shipping' => $address, 'meta' => [] ] ];
    $_GET = []; $_POST = []; $_FILES = []; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/address-book/';
}
function request_data( $type = 'billing', $extra = [] ) {
    $method = new ReflectionMethod( Ishi_WooCommerce_Addresses::class, 'revision' ); $method->setAccessible( true );
    $data = [ 'action' => Ishi_WooCommerce_Addresses::ACTION, 'ishi_address_type' => $type, 'ishi_address_nonce' => wp_create_nonce( Ishi_WooCommerce_Addresses::ACTION . '_' . $type ), 'ishi_address_revision' => $method->invoke( null, new WC_Customer( 5 ), $type ) ];
    foreach ( $GLOBALS['db'][5][ $type ] as $key => $value ) { $data[ $type . '_' . $key ] = $value; }
    return array_merge( $data, [ $type . '_first_name' => 'Updated' ], $extra );
}
function submit( $data ) { $_POST = $data; return Ishi_WooCommerce_Addresses::save_submission( $data ); }
function expect( $yes, $why ) { if ( ! $yes ) { throw new Exception( $why ); } }
function rejected( $result ) { expect( ! $result['success'] && $GLOBALS['writes'] === 0, 'Rejected input wrote data or returned success.' ); }
function test( $label, $callback ) { fixture(); $callback(); echo 'PASS ' . $label . PHP_EOL; }

test( 'default shows both address cards and standalone links', function () {
    $html = Ishi_WooCommerce_Addresses::render(); expect( substr_count( $html, 'woocommerce-Address-title' ) === 2 && strpos( $html, 'data-ishi-address-edit' ) !== false && strpos( $html, 'href=' ) === false && strpos( $html, '<form' ) === false, 'Incorrect default display.' );
} );
test( 'billing edit renders current customer fields', function () { select_view( 'billing' ); $html = Ishi_WooCommerce_Addresses::render(); expect( strpos( $html, 'name="billing_city" value="Makati"' ) !== false && strpos( $html, 'name="shipping_city"' ) === false, 'Wrong edit mode.' ); } );
test( 'shipping edit renders shipping fields', function () { select_view( 'shipping' ); $html = Ishi_WooCommerce_Addresses::render(); expect( strpos( $html, 'name="shipping_country"' ) !== false && strpos( $html, 'name="billing_country"' ) === false, 'Wrong shipping fields.' ); } );
test( 'billing changes only billing of authenticated customer', function () { $other = $GLOBALS['db'][9]; $shipping = $GLOBALS['db'][5]['shipping']; $r = submit( request_data( 'billing', [ 'customer_id' => 9, 'shipping_first_name' => 'attack' ] ) ); expect( $r['success'] && $GLOBALS['db'][5]['shipping'] === $shipping && $GLOBALS['db'][9] === $other, 'Address ownership/isolation failed.' ); } );
test( 'shipping changes only shipping', function () { $billing = $GLOBALS['db'][5]['billing']; $r = submit( request_data( 'shipping' ) ); expect( $r['success'] && $GLOBALS['db'][5]['billing'] === $billing, 'Billing was altered.' ); } );
test( 'anonymous requests blocked', function () { $p = request_data(); $GLOBALS['uid'] = 0; rejected( submit( $p ) ); } );
test( 'invalid and array address types blocked', function () { rejected( submit( [ 'ishi_address_type' => '../billing' ] ) ); rejected( submit( [ 'ishi_address_type' => [] ] ) ); } );
test( 'nonce is bound to address type', function () { $p = request_data( 'shipping' ); $p['ishi_address_nonce'] = wp_create_nonce( Ishi_WooCommerce_Addresses::ACTION . '_billing' ); rejected( submit( $p ) ); } );
test( 'nonce required', function () { rejected( submit( request_data( 'billing', [ 'ishi_address_nonce' => '' ] ) ) ); } );
test( 'stale edits rejected', function () { $p = request_data(); $GLOBALS['db'][5]['billing']['city'] = 'Different'; rejected( submit( $p ) ); } );
test( 'required field retains other entered values', function () { $r = submit( request_data( 'billing', [ 'billing_city' => '', 'billing_first_name' => 'Keep Me' ] ) ); rejected( $r ); expect( $r['values']['billing_first_name'] === 'Keep Me' && $r['type'] === 'billing', 'Values/edit mode not preserved.' ); } );
test( 'failed save renders edit view with entered values', function () { $r = submit( request_data( 'billing', [ 'billing_city' => '', 'billing_first_name' => 'Keep Me' ] ) ); $p = new ReflectionProperty( Ishi_WooCommerce_Addresses::class, 'response' ); $p->setAccessible( true ); $p->setValue( null, $r ); $html = Ishi_WooCommerce_Addresses::render(); expect( strpos( $html, 'value="Keep Me"' ) !== false && strpos( $html, '<form' ) !== false, 'Failed submission lost edit view.' ); } );
test( 'invalid country cannot write', function () { rejected( submit( request_data( 'billing', [ 'billing_country' => 'ZZ' ] ) ) ); } );
test( 'shipping country restriction enforced', function () { rejected( submit( request_data( 'shipping', [ 'shipping_country' => 'US' ] ) ) ); } );
test( 'invalid state rejected', function () { rejected( submit( request_data( 'billing', [ 'billing_state' => 'CA' ] ) ) ); } );
test( 'state name canonicalized', function () { $r = submit( request_data( 'billing', [ 'billing_state' => 'Metro Manila' ] ) ); expect( $r['success'] && $GLOBALS['db'][5]['billing']['state'] === '00', 'State normalization failed.' ); } );
test( 'native validators consulted', function () { rejected( submit( request_data( 'billing', [ 'billing_postcode' => 'bad', 'billing_email' => 'bad', 'billing_phone' => 'bad' ] ) ) ); } );
test( 'array injection blocked', function () { rejected( submit( request_data( 'billing', [ 'billing_city' => [ 'unexpected' ] ] ) ) ); } );
test( 'uploads rejected', function () { $_FILES = [ 'attachment' => [] ]; rejected( submit( request_data() ) ); } );
test( 'registered custom field saved through WC metadata', function () { $GLOBALS['filters']['woocommerce_billing_fields'] = function ( $f ) { $f['billing_gate_code'] = [ 'label' => 'Gate code' ]; return $f; }; $r = submit( request_data( 'billing', [ 'billing_gate_code' => 'Gate 4', 'billing_unregistered' => 'ignored' ] ) ); expect( $r['success'] && $GLOBALS['db'][5]['meta'] === [ 'billing_gate_code' => 'Gate 4' ], 'Custom field scope/persistence failed.' ); } );
test( 'field value filter preserved', function () { $GLOBALS['filters']['woocommerce_process_myaccount_field_billing_city'] = function ( $v ) { return 'Filtered'; }; $r = submit( request_data() ); expect( $r['success'] && $GLOBALS['db'][5]['billing']['city'] === 'Filtered', 'Value filter bypassed.' ); } );
test( 'extension validation prevents save without consuming other notices', function () { wc_add_notice( 'Unrelated checkout problem', 'error' ); $before = wc_get_notices(); $GLOBALS['actions']['woocommerce_after_save_address_validation'] = function () { wc_add_notice( 'Extension says no', 'error' ); }; $r = submit( request_data() ); rejected( $r ); expect( in_array( 'Extension says no', $r['errors'], true ) && wc_get_notices() === $before, 'Notice isolation failed.' ); } );
test( 'existing unrelated errors do not block a valid save', function () { wc_add_notice( 'Checkout problem', 'error' ); expect( submit( request_data() )['success'], 'Unrelated notice blocked address.' ); } );
test( 'native post-save hook has four arguments and fires once', function () { $r = submit( request_data() ); $events = array_values( array_filter( $GLOBALS['events'], function ( $e ) { return $e[0] === 'woocommerce_customer_save_address'; } ) ); expect( $r['success'] && count( $events ) === 1 && count( $events[0][1] ) === 4, 'Wrong hook signature or count.' ); } );
test( 'failed persistence does not claim success', function () { $GLOBALS['fail_save'] = true; expect( ! submit( request_data() )['success'], 'Failed save masked.' ); } );
test( 'silent persistence failure detected by reload', function () { $GLOBALS['silent_failure'] = true; expect( ! submit( request_data() )['success'], 'Silent write failure masked.' ); } );
test( 'extension post-save error retains editor', function () { $GLOBALS['actions']['woocommerce_customer_save_address'] = function () { wc_add_notice( 'Downstream failed', 'error' ); }; $r = submit( request_data() ); expect( ! $r['success'] && $r['type'] === 'billing', 'Post-save error masked.' ); } );
test( 'validation hook cannot change customer identity', function () { $GLOBALS['actions']['woocommerce_after_save_address_validation'] = function ( $id, $type, $fields, $customer ) { $customer->set_id( 9 ); }; rejected( submit( request_data() ) ); } );
test( 'country change reselects native field definitions', function () { $GLOBALS['filters']['woocommerce_billing_fields'] = function ( $f, $c ) { if ( $c === 'US' ) { $f['billing_city']['required'] = false; } return $f; }; $r = submit( request_data( 'billing', [ 'billing_country' => 'US', 'billing_state' => 'CA', 'billing_postcode' => '90210', 'billing_city' => '' ] ) ); expect( $r['success'], 'Country-specific fields ignored.' ); } );
test( 'ordinary POST is rejected before saving and never redirects', function () {
    $_POST = request_data(); $_SERVER['REQUEST_METHOD'] = 'POST';
    Ishi_WooCommerce_Addresses::handle_request();
    expect( $GLOBALS['writes'] === 0 && empty( $GLOBALS['redirects'] ) && empty( $GLOBALS['transients'] ), 'Normal POST saved or redirected.' );
    $html = Ishi_WooCommerce_Addresses::render();
    expect( strpos( $html, 'No changes were saved' ) !== false, 'Missing failure explanation.' );
} );
test( 'editor cannot submit natively before background script is ready', function () {
    select_view( 'billing' ); $html = Ishi_WooCommerce_Addresses::render();
    expect( strpos( $html, 'type="button" data-ishi-address-save' ) !== false && strpos( $html, 'data-ishi-address-ready disabled' ) !== false && strpos( $html, 'onsubmit="return false;"' ) !== false, 'Editor can submit natively.' );
} );
test( 'save handler runs before native form handlers and frontend routing', function () {
    $found = false;
    foreach ( $GLOBALS['registrations'] as $registration ) {
        if ( $registration[0] === 'wp_loaded' && $registration[1] === [ 'Ishi_WooCommerce_Addresses', 'handle_request' ] && $registration[2] < 10 ) { $found = true; }
    }
    expect( $found, 'Address handler registered too late.' );
} );
test( 'editor has no Cancel link or native account action', function () {
    select_view( 'billing' ); $html = Ishi_WooCommerce_Addresses::render();
    expect( strpos( $html, 'Cancel' ) === false && strpos( $html, 'value="edit_address"' ) === false && strpos( $html, 'woocommerce-edit-address-nonce' ) === false, 'Native layout/action returned.' );
} );
test( 'save callbacks are not intercepted by a redirect guard', function () {
    $GLOBALS['actions']['woocommerce_customer_save_address'] = function () { wp_safe_redirect( 'https://example.test/my-account/' ); };
    $r = submit( request_data() );
    expect( $r['success'] && $GLOBALS['redirects'] === [ [ 'url' => 'https://example.test/my-account/', 'status' => 302 ] ], 'Callback redirect was intercepted.' );
    // This callback deliberately returns. A real callback that exits would end the request;
    // this test makes no claim to prevent that behavior.
} );


test( 'REST permissions reject anonymous users and missing or invalid nonces', function () {
    $r = new AddressRequest( 'billing' ); $GLOBALS['uid'] = 0;
    expect( Ishi_WooCommerce_Addresses::rest_read( $r ) instanceof WP_Error, 'Anonymous request allowed.' );
    $GLOBALS['uid'] = 5; $r->nonce = '';
    expect( Ishi_WooCommerce_Addresses::rest_save( $r ) instanceof WP_Error && $GLOBALS['writes'] === 0, 'Missing REST nonce allowed.' );
    $r->nonce = 'bad'; expect( Ishi_WooCommerce_Addresses::rest_read( $r ) instanceof WP_Error, 'Invalid REST nonce allowed.' );
} );
test( 'REST success returns verified cards without redirects and preserves POST context', function () {
    $original = $_POST = [ 'untouched' => 'yes' ];
    $r = Ishi_WooCommerce_Addresses::rest_save( new AddressRequest( 'billing', wp_unslash( request_data() ) ) );
    expect( $r->status === 200 && $r->data['saved'] === true && strpos( $r->data['html'], 'woocommerce-Addresses' ) !== false, 'Missing successful cards.' );
    expect( $_POST === $original && empty( $GLOBALS['redirects'] ), 'Context or navigation changed.' );
} );
test( 'REST validation returns 422 and preserves entered values', function () {
    $body = wp_unslash( request_data( 'billing', [ 'billing_city' => '', 'billing_first_name' => 'Retained' ] ) );
    $r = Ishi_WooCommerce_Addresses::rest_save( new AddressRequest( 'billing', $body ) );
    expect( $r->status === 422 && $r->data['saved'] === false && strpos( $r->data['html'], 'Retained' ) !== false && $GLOBALS['writes'] === 0, 'Invalid REST save.' );
} );
test( 'REST route type cannot be overwritten by body fields', function () {
    $r = Ishi_WooCommerce_Addresses::rest_save( new AddressRequest( 'shipping', wp_unslash( request_data() ) ) );
    expect( $r instanceof WP_Error && $GLOBALS['writes'] === 0, 'Conflicting type accepted.' );
    expect( Ishi_WooCommerce_Addresses::rest_read( new AddressRequest( '../billing' ) ) instanceof WP_Error, 'Invalid route accepted.' );
} );
test( 'REST read selects shipping and is private and non-cacheable', function () {
    $r = Ishi_WooCommerce_Addresses::rest_read( new AddressRequest( 'shipping' ) );
    expect( $r->data['view'] === 'shipping' && strpos( $r->data['html'], 'name="shipping_country"' ) !== false && $r->headers['Cache-Control'] === 'private, no-store, max-age=0', 'Invalid editor response.' );
} );
test( 'REST input slashing preserves apostrophes and custom validation context', function () {
    $body = wp_unslash( request_data() ); $body['billing_first_name'] = "O'Brien";
    $r = Ishi_WooCommerce_Addresses::rest_save( new AddressRequest( 'billing', $body ) );
    expect( $r->data['saved'] && $GLOBALS['db'][5]['billing']['first_name'] === "O'Brien", 'Slashing corrupted value.' );
} );
test( 'page query parameters cannot change shortcode view', function () {
    $_GET['ishi_address'] = 'shipping';
    expect( strpos( Ishi_WooCommerce_Addresses::render(), 'woocommerce-Addresses' ) !== false, 'Page query selected editor.' );
} );
echo "All address adapter tests passed." . PHP_EOL;
