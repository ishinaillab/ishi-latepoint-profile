<?php
/** Presentation context for the standalone shortcodes; never enables account routing. */
defined( 'ABSPATH' ) || exit;

final class Ishi_Theme_Compatibility {
    public static function enqueue_dropdown_scheme() {
        if ( function_exists( 'qwery_enqueue_styles' ) ) {
            // Delegated events also cover builder content and later REST fragments.
            wp_enqueue_script( 'ishi-dropdown-scheme', plugins_url( 'assets/dropdown-scheme.js', dirname( __DIR__ ) . '/ishi-latepoint-profile.php' ), [ 'jquery' ], '1.3.13', true );
        }
    }

    public static function enqueue_select_base_early() {
        // WC registers select2 at priority 10; Qwery's skin CSS loads at 1000+.
        // Builder content may not be discoverable until after wp_head, so do
        // not depend on post_content to put this base stylesheet before Qwery.
        // Reuse WC's handle: no duplicate URL, copied CSS, or extra JavaScript.
        if ( function_exists( 'qwery_enqueue_styles' ) && wp_style_is( 'select2', 'registered' ) ) {
            wp_enqueue_style( 'select2' );
        }
    }

    public static function enqueue_if_present() {
        $post = get_post();
        if ( $post && ( has_shortcode( $post->post_content, 'ishi_customer_addresses' ) || has_shortcode( $post->post_content, 'ishi_latepoint_profile' ) ) ) {
            self::enqueue();
        }
    }

    public static function enqueue() {
        $dependencies = [];
        foreach ( [ 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'select2' ] as $handle ) {
            if ( wp_style_is( $handle, 'registered' ) ) {
                wp_enqueue_style( $handle );
                $dependencies[] = $handle;
            }
        }
        // Use Qwery's active skin/child-theme file resolver and existing handles.
        // Do not call its combined frontend loader: that also adds account JavaScript.
        if ( function_exists( 'qwery_enqueue_styles' ) ) {
            qwery_enqueue_styles( [
                'qwery-woocommerce' => [ 'src' => 'plugins/woocommerce/woocommerce.css' ],
                'qwery-woocommerce-responsive' => [ 'src' => 'plugins/woocommerce/woocommerce-responsive.css', 'media' => 'all' ],
            ], 'woocommerce' );
        }
        foreach ( [ 'qwery-woocommerce', 'qwery-woocommerce-responsive' ] as $handle ) {
            if ( wp_style_is( $handle, 'registered' ) ) { $dependencies[] = $handle; }
        }
        wp_enqueue_style( 'ishi-theme-compatibility', plugins_url( 'assets/theme-compatibility.css', dirname( __DIR__ ) . '/ishi-latepoint-profile.php' ), $dependencies, '1.3.7' );
    }

    public static function render_styles() {
        // Builders may keep shortcode content outside post_content. Print any styles
        // not already emitted in wp_head; WordPress tracks handles to avoid duplicates.
        // REST fragments use the assets already loaded by the initial document.
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }
        self::enqueue();
        wp_print_styles( [ 'ishi-theme-compatibility' ] );
    }
}
add_action( 'wp_enqueue_scripts', [ 'Ishi_Theme_Compatibility', 'enqueue_dropdown_scheme' ], 20 );
add_action( 'wp_enqueue_scripts', [ 'Ishi_Theme_Compatibility', 'enqueue_select_base_early' ], 20 );
add_action( 'wp_enqueue_scripts', [ 'Ishi_Theme_Compatibility', 'enqueue_if_present' ], 2100 );
