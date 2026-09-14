<?php
/**
 * Plugin Name: Ishi Address Redirect Trace
 * Description: Diagnostic-only helper for tracing redirect attempts during Ishi billing/shipping address saves.
 * Version: 0.1.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

final class Ishi_Address_Redirect_Trace {
    const SOURCE = 'ishi-address-redirect-trace';
    const ACTION = 'ishi_save_customer_address';

    public static function boot() {
        // Observe immediately before Ishi_WooCommerce_Addresses' PHP_INT_MAX redirect guard.
        add_filter( 'wp_redirect', [ __CLASS__, 'trace_redirect' ], PHP_INT_MAX - 1, 2 );
    }

    private static function is_target_request() {
        return ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST'
            && isset( $_POST['action'] )
            && is_string( $_POST['action'] )
            && wp_unslash( $_POST['action'] ) === self::ACTION;
    }

    private static function clean_backtrace() {
        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 35 );
        $clean = [];

        foreach ( $trace as $frame ) {
            $function = '';
            if ( isset( $frame['class'] ) ) { $function .= $frame['class']; }
            if ( isset( $frame['type'] ) ) { $function .= $frame['type']; }
            if ( isset( $frame['function'] ) ) { $function .= $frame['function']; }

            $clean[] = [
                'function' => $function,
                'file'     => isset( $frame['file'] ) ? $frame['file'] : '',
                'line'     => isset( $frame['line'] ) ? (int) $frame['line'] : 0,
            ];
        }

        return $clean;
    }

    private static function log( $message, $context ) {
        $context = array_merge( [ 'source' => self::SOURCE ], $context );

        if ( function_exists( 'wc_get_logger' ) ) {
            try {
                wc_get_logger()->debug( $message, $context );
                return;
            } catch ( Throwable $e ) {
                // Fall through to PHP's error log if WooCommerce logging is unavailable.
            }
        }

        $payload = wp_json_encode( [ 'message' => $message, 'context' => $context ] );
        if ( is_string( $payload ) ) {
            error_log( '[Ishi Address Redirect Trace] ' . $payload );
        }
    }

    public static function trace_redirect( $location, $status = 302 ) {
        if ( ! self::is_target_request() ) {
            return $location;
        }

        global $wp_current_filter;

        self::log(
            'Redirect attempted during Ishi address save.',
            [
                'redirect_location' => is_string( $location ) ? $location : '',
                'redirect_status'   => (int) $status,
                'request_uri'       => isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] )
                    ? wp_unslash( $_SERVER['REQUEST_URI'] )
                    : '',
                'hook_stack'        => is_array( $wp_current_filter ) ? array_values( $wp_current_filter ) : [],
                'backtrace'         => self::clean_backtrace(),
            ]
        );

        // Observation only. Do not alter the requested redirect.
        return $location;
    }
}

Ishi_Address_Redirect_Trace::boot();
