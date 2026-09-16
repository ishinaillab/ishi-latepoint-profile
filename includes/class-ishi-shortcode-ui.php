<?php
/** Shared frontend contract. Domain controllers retain ownership and persistence rules. */
defined( 'ABSPATH' ) || exit;

final class Ishi_Shortcode_UI {
    const VERSION = '1.4.1';
    const REST_NAMESPACE = 'ishi-profile/v1';
    private static $shortcodes = [];

    public static function register( $shortcode, $callback ) {
        self::$shortcodes[ $shortcode ] = true;
        add_shortcode( $shortcode, static function ( $atts = [], $content = null, $tag = '' ) use ( $callback ) {
            self::no_cache();
            self::enqueue();
            return call_user_func( $callback, $atts, $content, $tag );
        } );
    }

    public static function present( $content ) {
        foreach ( array_keys( self::$shortcodes ) as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) { return true; }
        }
        return false;
    }

    public static function no_cache() {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        if ( ! headers_sent() ) { nocache_headers(); }
    }

    public static function enqueue() {
        // Builders can inject forms after wp_head. Delegation covers future fragments.
        wp_enqueue_script( 'ishi-shortcode-ui', plugins_url( 'assets/shortcode-ui.js', dirname( __DIR__ ) . '/ishi-latepoint-profile.php' ), [], self::VERSION, false );
    }

    public static function permission( $request ) {
        self::no_cache();
        if ( ! is_user_logged_in() || get_current_user_id() <= 0 ) {
            return new WP_Error( 'ishi_auth', __( 'Please sign in to manage your account.', 'ishi-latepoint-profile' ), [ 'status' => 401 ] );
        }
        if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            return new WP_Error( 'ishi_nonce', __( 'Your session has expired. Refresh before saving.', 'ishi-latepoint-profile' ), [ 'status' => 403 ] );
        }
        return true;
    }

    public static function root_attributes( $component, $extra_class = '' ) {
        self::enqueue();
        // Reuse WC enhancement handles for every component, including future select fields.
        foreach ( [ 'selectWoo', 'wc-country-select', 'wc-address-i18n' ] as $handle ) {
            if ( function_exists( 'wp_script_is' ) && wp_script_is( $handle, 'registered' ) ) { wp_enqueue_script( $handle ); }
        }
        return 'class="woocommerce woocommerce-page woocommerce-account ishi-theme-account ' . esc_attr( $extra_class ) .
            '" data-ishi-ui="' . esc_attr( $component ) . '" data-ishi-rest-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '"';
    }

    public static function response( $component, $html, $saved, $view = '', $extra = [] ) {
        $nonce = wp_create_nonce( 'wp_rest' );
        $response = new WP_REST_Response( array_merge( $extra, [
            'ishi_ui' => true, 'component' => $component, 'saved' => $saved,
            'view' => $view, 'html' => $html, 'rest_nonce' => $nonce,
        ] ), $saved === false ? 422 : 200 );
        $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
        // Core REST authentication may have generated this header before a password change.
        $response->header( 'X-WP-Nonce', $nonce );
        return $response;
    }

    /**
     * Make nonces generated later in THIS request use the newly issued session.
     * Only called by WordPress's authenticated cookie action during the save.
     * Does not create a login, accept a client token, or change customer ownership.
     */
    public static function renewed_cookie( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
        if ( defined( 'LOGGED_IN_COOKIE' ) && (int) $user_id === get_current_user_id() && $scheme === 'logged_in' ) {
            $_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
        }
    }
}
add_action( 'wp_enqueue_scripts', [ 'Ishi_Shortcode_UI', 'enqueue' ], 30 );
