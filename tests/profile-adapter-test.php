<?php
/** Domain doubles exercise the real Profile service, REST adapter and templates. */
require __DIR__ . '/address-adapter-test.php';
define( 'LATEPOINT_PARAMS_SCOPE_CUSTOMER', 'customer' );
define( 'LOGGED_IN_COOKIE', 'test_logged_in' );
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function sanitize_text_field( $v ) { return trim( strip_tags( $v ) ); }
function sanitize_email( $v ) { return filter_var( $v, FILTER_SANITIZE_EMAIL ); }
function email_exists( $v ) { return $v === 'taken@example.test' ? 99 : false; }
function get_userdata( $id ) { return $id === 5 ? clone $GLOBALS['profile_user'] : false; }
function wp_set_current_user( $id ) { $GLOBALS['uid'] = $id; return get_userdata( $id ); }
function wp_update_user( $data ) { foreach ( wp_unslash( $data ) as $k => $v ) { $GLOBALS['profile_user']->$k = $v; } return 5; }
function wp_check_password( $plain, $hash, $id = 0 ) { return password_verify( $plain, $hash ); }
function wp_unique_id( $prefix ) { static $n = 0; return $prefix . ++$n; }
function remove_action( $tag, $callback, $priority = 10 ) {
    $GLOBALS['registrations'] = array_filter( $GLOBALS['registrations'], function ( $r ) use ( $tag, $callback ) { return $r[0] !== $tag || $r[1] !== $callback; } );
}
function wp_die( ...$args ) { throw new RuntimeException( 'Obsolete form rejected' ); }
class OsAuthHelper {
    public static function is_customer_auth_disabled() { return false; }
    public static function can_wp_users_login_as_customers() { return $GLOBALS['wp_auth']; }
    public static function get_logged_in_customer_id() { return $GLOBALS['local_id']; }
    public static function verify_password( $p, $h ) { return password_verify( $p, $h ); }
}
class OsCustomerHelper { public static function is_wp_user_safe_for_customer_link( $id ) { return $id === 5; } }
class OsSettingsHelper { public static function get_default_fields_for_customer() { return [ 'first_name' => [ 'active' => true, 'required' => true ] ]; } }
class OsSessionsHelper { public static function get_customer_session_cookie() { return '1||' . ( time() + 3600 ) . '||test'; } }
class OsSessionModel {
    public $session_key = 9, $expiration;
    public function __construct( $id ) { $this->expiration = time() + 3600; }
    public function is_new_record() { return false; }
}
class OsCustomerModel {
    public $id, $wordpress_user_id, $first_name, $last_name, $email, $phone, $password, $updated_at, $custom_fields;
    public function __construct( $id = null ) { foreach ( $GLOBALS['profile_customer'] as $k => $v ) { $this->$k = $v; } }
    public function where( $v ) { return $this; }
    public function set_limit( $v ) { return $this; }
    public function get_results_as_models() { return $GLOBALS['linked'] ? [ new self(9) ] : []; }
    public function is_new_record() { return false; }
    public function set_data( $v, $scope ) { foreach ( $v as $k => $x ) { $this->$k = sanitize_text_field( $x ); } }
    public function validate() { return $this->first_name !== ''; }
    public function get_error_messages( $type = '' ) { return [ 'First name required' ]; }
    public function get_data_vars() { return get_object_vars( $this ); }
    public function save() {
        $GLOBALS['profile_writes']++;
        if ( $GLOBALS['profile_fail'] ) { return false; }
        $GLOBALS['profile_customer'] = get_object_vars( $this );
        foreach ( [ 'first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'user_email' ] as $lp => $wp ) {
            if ( $this->$lp !== '' ) { $GLOBALS['profile_user']->$wp = $this->$lp; }
        }
        return true;
    }
    public function update_password( $plain ) {
        $GLOBALS['profile_customer']['password'] = password_hash( $plain, PASSWORD_DEFAULT );
        if ( $GLOBALS['wp_auth'] ) {
            $GLOBALS['profile_user']->user_pass = password_hash( $plain, PASSWORD_DEFAULT );
            foreach ( $GLOBALS['registrations'] as $r ) {
                if ( $r[0] === 'set_logged_in_cookie' ) { $r[1]( 'new-session-cookie', 0, time() + 3600, 5, 'logged_in', 'new-token' ); }
            }
        }
        return true;
    }
}
require dirname( __DIR__ ) . '/ishi-latepoint-profile.php';
function profile_fixture() {
    fixture();
    $GLOBALS['wp_auth'] = true;
    $GLOBALS['local_id'] = 9;
    $GLOBALS['linked'] = true;
    $GLOBALS['profile_fail'] = false;
    $GLOBALS['profile_writes'] = 0;
    $_COOKIE[ LOGGED_IN_COOKIE ] = 'original-session';
    $GLOBALS['profile_user'] = (object) [ 'ID' => 5, 'display_name' => 'Display', 'first_name' => 'Old', 'last_name' => 'Name', 'user_email' => 'old@example.test', 'user_pass' => password_hash( 'current password long', PASSWORD_DEFAULT ) ];
    $GLOBALS['profile_customer'] = [ 'id' => 9, 'wordpress_user_id' => 5, 'first_name' => 'Old', 'last_name' => 'Name', 'phone' => '1234', 'email' => 'old@example.test', 'password' => password_hash( 'current password long', PASSWORD_DEFAULT ), 'updated_at' => '2026-09-16', 'custom_fields' => [] ];
}
function profile_body( $changes = [] ) {
    $ctx = Ishi_LatePoint_Profile::context();
    return array_merge( [
        'account_first_name' => 'New', 'account_last_name' => 'Name', 'account_email' => 'new@example.test',
        'account_phone' => '9876', 'account_display_name' => 'New Display',
        'ishi_lp_profile_nonce' => wp_create_nonce( Ishi_LatePoint_Profile::nonce_action( $ctx ) ),
        'ishi_lp_revision' => Ishi_LatePoint_Profile::revision( $ctx ),
    ], $changes );
}
function profile_test( $name, $fn ) { profile_fixture(); $fn(); echo 'PASS profile: ' . $name . PHP_EOL; }
function profile_save( $body ) { return Ishi_LatePoint_Profile::rest_save( new AddressRequest( '', $body ) ); }
profile_test( 'successful REST save verifies LP and WP values without navigation', function () {
    $r = profile_save( profile_body() );
    expect( $r->status === 200 && $r->data['saved'] === true, 'Save failed' );
    expect( $GLOBALS['profile_customer']['first_name'] === 'New' && $GLOBALS['profile_user']->display_name === 'New Display', 'Wrong stores' );
    expect( strpos( $r->data['html'], 'data-ishi-ui="profile"' ) !== false && strpos( $r->data['html'], 'admin-post.php' ) === false && empty( $GLOBALS['redirects'] ), 'Wrong transport' );
} );
profile_test( 'validation retains non-secret input and never renders passwords', function () {
    $r = profile_save( profile_body( [ 'account_first_name' => '', 'account_display_name' => 'Retained', 'password_current' => 'secret-do-not-echo' ] ) );
    expect( $r->status === 422 && ! $r->data['saved'] && $GLOBALS['profile_writes'] === 0, 'Validation bypassed' );
    expect( strpos( $r->data['html'], 'Retained' ) !== false && strpos( $r->data['html'], 'secret-do-not-echo' ) === false, 'Incorrect retention' );
} );
profile_test( 'anonymous and invalid REST nonce cannot write', function () {
    $request = new AddressRequest( '', profile_body() ); $request->nonce = 'bad';
    expect( is_wp_error( Ishi_LatePoint_Profile::rest_save( $request ) ), 'Bad nonce accepted' );
    $GLOBALS['uid'] = 0;
    expect( is_wp_error( Ishi_LatePoint_Profile::rest_save( $request ) ) && $GLOBALS['profile_writes'] === 0, 'Anonymous write' );
} );
profile_test( 'field nonce, revision, email conflict and uploads prevent writes', function () {
    foreach ( [ [ 'ishi_lp_profile_nonce' => '' ], [ 'ishi_lp_revision' => 'old' ], [ 'account_email' => 'taken@example.test' ], [ 'account_first_name' => [ 'bad' ] ] ] as $change ) {
        expect( ! profile_save( profile_body( $change ) )->data['saved'], 'Invalid input accepted' );
    }
    $r = new AddressRequest( '', profile_body() ); $r->files = [ 'upload' => [ 'name' => 'bad.txt' ] ];
    expect( ! Ishi_LatePoint_Profile::rest_save( $r )->data['saved'] && $GLOBALS['profile_writes'] === 0, 'Upload accepted' );
} );
profile_test( 'linked customer identity remains server controlled', function () {
    $body = profile_body( [ 'customer_id' => 123, 'wordpress_user_id' => 123 ] );
    expect( profile_save( $body )->data['saved'] && $GLOBALS['profile_customer']['id'] === 9, 'Client identity used' );
    $GLOBALS['linked'] = false;
    expect( ! profile_save( $body )->data['saved'], 'Unlinked account accepted' );
} );
profile_test( 'partial failure displays stored values and never claims success', function () {
    $GLOBALS['profile_fail'] = true; $r = profile_save( profile_body() );
    expect( ! $r->data['saved'] && strpos( $r->data['html'], 'Some changes may already' ) !== false && strpos( $r->data['html'], 'value="Old"' ) !== false, 'Partial failure hidden' );
} );
profile_test( 'password changes renew nonces for the newly issued session', function () {
    $old_nonce = wp_create_nonce( 'wp_rest' );
    $r = profile_save( profile_body( [ 'password_current' => 'current password long', 'password_1' => 'replacement password long', 'password_2' => 'replacement password long' ] ) );
    expect( $r->data['saved'] && wp_check_password( 'replacement password long', $GLOBALS['profile_user']->user_pass ), 'Password failed' );
    expect( $r->data['rest_nonce'] !== $old_nonce && $r->headers['X-WP-Nonce'] === wp_create_nonce( 'wp_rest' ), 'Old session nonce returned' );
    expect( strpos( $r->data['html'], wp_create_nonce( Ishi_LatePoint_Profile::nonce_action( Ishi_LatePoint_Profile::context() ) ) ) !== false, 'Old form nonce returned' );
    expect( profile_save( profile_body() )->data['saved'], 'Next save failed after password change' );
} );
profile_test( 'local LP authentication does not change WordPress password', function () {
    $GLOBALS['wp_auth'] = false; $hash = $GLOBALS['profile_user']->user_pass;
    $r = profile_save( profile_body( [ 'password_current' => 'current password long', 'password_1' => 'replacement password long', 'password_2' => 'replacement password long' ] ) );
    expect( $r->data['saved'] && $GLOBALS['profile_user']->user_pass === $hash, 'Wrong password store' );
    $GLOBALS['local_id'] = 123;
    expect( ! profile_save( [] )->data['saved'], 'Wrong local customer accepted' );
} );
profile_test( 'old normal POST cannot save or redirect', function () {
    try { Ishi_LatePoint_Profile::handle_post(); } catch ( RuntimeException $e ) {}
    expect( $GLOBALS['profile_writes'] === 0 && empty( $GLOBALS['redirects'] ), 'Legacy submission wrote' );
} );
echo "All profile adapter tests passed." . PHP_EOL;
