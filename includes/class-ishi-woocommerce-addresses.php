<?php
/** Standalone address controller. Native behavior audited against the supplied WooCommerce 11.0.1 source. */
defined( 'ABSPATH' ) || exit;

final class Ishi_WooCommerce_Addresses {
    const SHORTCODE = 'ishi_customer_addresses';
    const ACTION = 'ishi_save_customer_address';
    const MODE = 'ishi_address';
    private static $response = null;
    private static $rendered = false;

    public static function boot() {
        add_shortcode( self::SHORTCODE, [ __CLASS__, 'render' ] );
        // Process before WooCommerce form handlers and frontend routing redirects.
        add_action( 'wp_loaded', [ __CLASS__, 'handle_request' ], 5 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_if_present' ], 30 );
    }

    private static function available() {
        return function_exists( 'WC' ) && class_exists( 'WC_Customer' ) && class_exists( 'WC_Validation' ) &&
            did_action( 'woocommerce_init' ) && WC()->countries;
    }

    private static function no_cache() {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        if ( ! headers_sent() ) { nocache_headers(); }
    }

    public static function enqueue_if_present() {
        $post = get_post();
        if ( $post && has_shortcode( $post->post_content, self::SHORTCODE ) ) { self::assets(); }
    }

    private static function assets() {
        if ( ! self::available() ) { return; }
        // Same handles as WC_Shortcode_My_Account::edit_address(), without loading that controller.
        wp_enqueue_script( 'wc-country-select' );
        wp_enqueue_script( 'wc-address-i18n' );
        foreach ( [ 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen' ] as $handle ) {
            if ( wp_style_is( $handle, 'registered' ) ) { wp_enqueue_style( $handle ); }
        }
    }

    public static function valid_type( $type ) {
        return is_string( $type ) && in_array( $type, [ 'billing', 'shipping' ], true );
    }

    private static function customer() {
        if ( ! is_user_logged_in() || get_current_user_id() <= 0 ) {
            throw new RuntimeException( __( 'Please sign in to manage your addresses.', 'ishi-latepoint-profile' ) );
        }
        $customer = new WC_Customer( get_current_user_id() );
        if ( (int) $customer->get_id() !== get_current_user_id() ) {
            throw new RuntimeException( __( 'Your customer account could not be loaded.', 'ishi-latepoint-profile' ) );
        }
        return $customer;
    }

    public static function base_url() {
        $home = wp_parse_url( home_url( '/' ) );
        $path = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        if ( substr( $path, 0, 1 ) !== '/' || substr( $path, 0, 2 ) === '//' ) { return home_url( '/' ); }
        $url = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . $path;
        return remove_query_arg( [ self::MODE, 'ishi_address_notice' ], $url );
    }

    private static function countries( $type ) {
        return $type === 'billing' ? WC()->countries->get_allowed_countries() : WC()->countries->get_shipping_countries();
    }

    /** Only fields defined by server-side WooCommerce filters, in the chosen address namespace. */
    private static function fields( $customer, $type, $country, $for_display = false ) {
        $fields = WC()->countries->get_address_fields( $country, $type . '_' );
        foreach ( $fields as $key => &$field ) {
            $field['value'] = self::value( $customer, $key );
            if ( $for_display ) {
                if ( ! $field['value'] && in_array( $key, [ 'billing_email', 'shipping_email' ], true ) ) {
                    $field['value'] = wp_get_current_user()->user_email;
                }
                $field['value'] = apply_filters( 'woocommerce_my_account_edit_address_field_value', $field['value'], $key, $type );
            }
        }
        unset( $field );
        // Reuse this server-side field filter for both rendering and validation, avoiding a display/save mismatch.
        $fields = apply_filters( 'woocommerce_address_to_edit', $fields, $type );
        if ( ! is_array( $fields ) ) { throw new RuntimeException( 'Invalid address field configuration.' ); }
        foreach ( $fields as $key => $field ) {
            if ( ! is_string( $key ) || ! preg_match( '/^' . $type . '_[a-zA-Z0-9_]+$/D', $key ) || ! is_array( $field ) ) {
                throw new RuntimeException( 'Address fields must belong to the selected address type.' );
            }
        }
        if ( ! isset( $fields[ $type . '_country' ] ) ) { throw new RuntimeException( 'The country field is required by this address interface.' ); }
        return $fields;
    }

    private static function value( $customer, $key ) {
        $getter = 'get_' . $key;
        return is_callable( [ $customer, $getter ] ) ? $customer->$getter( 'edit' ) : $customer->get_meta( $key, true, 'edit' );
    }

    private static function revision( $customer, $type ) {
        $getter = $type === 'billing' ? 'get_billing' : 'get_shipping';
        // Metadata is included so an already-stale edit cannot overwrite an extension's saved change.
        $meta = [];
        foreach ( $customer->get_meta_data() as $item ) {
            $data = $item->get_data();
            if ( strpos( $data['key'], $type . '_' ) === 0 ) { $meta[ $data['key'] ] = $data['value']; }
        }
        ksort( $meta );
        return wp_hash( wp_json_encode( [ $customer->get_id(), $type, $customer->$getter( 'edit' ), $meta ] ) );
    }

    private static function template( $name ) {
        return dirname( __DIR__ ) . '/templates/' . $name;
    }

    public static function render() {
        if ( self::$rendered ) { return ''; } // Native WC locale scripts use one set of address field IDs.
        self::$rendered = true;
        self::no_cache();
        if ( ! self::available() ) {
            return self::messages( [ __( 'Address management requires WooCommerce to be active.', 'ishi-latepoint-profile' ) ] );
        }
        if ( ! is_user_logged_in() ) {
            return self::messages( [ __( 'Please sign in to manage your addresses.', 'ishi-latepoint-profile' ) ] );
        }
        $level = ob_get_level();
        try {
            $customer = self::customer();
            $base_url = self::base_url();
            $mode = self::$response !== null ? self::$response['type'] : ( $_GET[ self::MODE ] ?? '' );
            if ( $mode !== '' && ! self::valid_type( $mode ) ) {
                return self::messages( [ __( 'Choose either Billing or Shipping to edit.', 'ishi-latepoint-profile' ) ] );
            }
            $feedback = self::$response !== null ? self::messages( self::$response['errors'] ) : self::success_notice();
            self::assets();
            $ishi_address_template = true;
            ob_start();
            echo '<div class="woocommerce ishi-customer-addresses">' . $feedback;
            if ( $mode === '' ) {
                $customer_id = $customer->get_id();
                // Both cards are intentional, even if checkout ships to billing only.
                $get_addresses = [ 'billing' => __( 'Billing address', 'woocommerce' ), 'shipping' => __( 'Shipping address', 'woocommerce' ) ];
                $titles = apply_filters( 'woocommerce_my_account_get_addresses', $get_addresses, $customer_id );
                foreach ( $get_addresses as $key => $title ) {
                    if ( is_array( $titles ) && isset( $titles[ $key ] ) && is_string( $titles[ $key ] ) ) { $get_addresses[ $key ] = $titles[ $key ]; }
                }
                include self::template( 'ishi-my-address.php' );
            } else {
                $load_address = $mode;
                $allowed = self::countries( $mode );
                if ( ! $allowed ) { throw new RuntimeException( 'No permitted countries are configured for this address.' ); }
                $country = self::$response['values'][ $mode . '_country' ] ?? self::value( $customer, $mode . '_country' );
                if ( ! is_string( $country ) || ! isset( $allowed[ $country ] ) ) {
                    $country = WC()->countries->get_base_country();
                    if ( ! isset( $allowed[ $country ] ) ) { $country = key( $allowed ); }
                }
                $address = self::fields( $customer, $mode, $country, true );
                foreach ( $address as $key => &$field ) {
                    if ( self::$response !== null && array_key_exists( $key, self::$response['values'] ) ) {
                        $field['value'] = self::$response['values'][ $key ];
                    } elseif ( ! array_key_exists( 'value', $field ) ) {
                        $field['value'] = self::value( $customer, $key );
                    }
                    if ( $key === $mode . '_country' ) { $field['value'] = $country; }
                }
                unset( $field );
                $revision = self::revision( $customer, $mode );
                include self::template( 'ishi-form-edit-address.php' );
            }
            echo '</div>';
            return ob_get_clean();
        } catch ( Throwable $e ) {
            while ( ob_get_level() > $level ) { ob_end_clean(); }
            return self::messages( [ __( 'The address form could not be loaded. Please contact support.', 'ishi-latepoint-profile' ) ] );
        }
    }

    private static function input_value( $raw, $field ) {
        $multiple = ( isset( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) && array_key_exists( 'multiple', $field['custom_attributes'] ) && $field['custom_attributes']['multiple'] !== false ) || ( $field['type'] ?? '' ) === 'multiselect';
        if ( is_array( $raw ) ) {
            if ( ! $multiple || count( $raw ) > 100 ) { throw new InvalidArgumentException( 'Invalid field value.' ); }
            foreach ( $raw as $v ) {
                if ( ! is_string( $v ) || strlen( $v ) > 4096 || strpos( $v, "\0" ) !== false ) { throw new InvalidArgumentException( 'Invalid field value.' ); }
            }
        } elseif ( ! is_string( $raw ) || strlen( $raw ) > 4096 || strpos( $raw, "\0" ) !== false ) {
            throw new InvalidArgumentException( 'Invalid field value.' );
        }
        return wc_clean( wp_unslash( $raw ) );
    }

    private static function failure( $type, $errors, $values = [] ) {
        return [ 'success' => false, 'type' => self::valid_type( $type ) ? $type : '', 'errors' => $errors, 'values' => $values ];
    }

    /** Receives WP's still-slashed POST array; native value filters and validation callbacks see normal $_POST. */
    public static function save_submission( $post ) {
        $type = is_array( $post ) ? ( $post['ishi_address_type'] ?? '' ) : '';
        if ( ! self::valid_type( $type ) ) { return self::failure( '', [ __( 'Invalid address type.', 'ishi-latepoint-profile' ) ] ); }
        if ( ! self::available() || ! is_user_logged_in() ) { return self::failure( $type, [ __( 'Please sign in with WooCommerce available before saving.', 'ishi-latepoint-profile' ) ] ); }
        $nonce = $post['ishi_address_nonce'] ?? '';
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( wp_unslash( $nonce ), self::ACTION . '_' . $type ) ) {
            return self::failure( $type, [ __( 'This form has expired. Please review it and try again.', 'ishi-latepoint-profile' ) ] );
        }
        $values = [];
        $started = false;
        $previous_notices = null;
        $redirect_guard = static function ( $location ) {
            // Stop redirecting callbacks before their following exit can abandon this handler.
            throw new RuntimeException( 'An address-save extension attempted to redirect the request.' );
        };
        add_filter( 'wp_redirect', $redirect_guard, PHP_INT_MAX );
        try {
            if ( ! empty( $_FILES ) ) { return self::failure( $type, [ __( 'This address form does not accept file uploads.', 'ishi-latepoint-profile' ) ] ); }
            $customer = self::customer();
            $user_id = $customer->get_id();
            $revision = $post['ishi_address_revision'] ?? '';
            if ( ! is_string( $revision ) || ! hash_equals( self::revision( $customer, $type ), $revision ) ) {
                return self::failure( $type, [ __( 'This address changed after you opened the form. Review the current address before trying again.', 'ishi-latepoint-profile' ) ] );
            }
            if ( ! WC()->session ) { WC()->initialize_session(); }
            if ( ! WC()->session ) { throw new RuntimeException( 'WooCommerce notices are unavailable.' ); }
            // Capture only notices from this operation. Do not erase or display unrelated checkout notices.
            $previous_notices = wc_get_notices();
            wc_set_notices( [] );
            $country_key = $type . '_country';
            $country = self::input_value( $post[ $country_key ] ?? '', [] );
            $country = apply_filters( 'woocommerce_process_myaccount_field_' . $country_key, $country );
            $allowed = self::countries( $type );
            if ( ! is_string( $country ) || ! isset( $allowed[ $country ] ) ) {
                // Keep legitimate scalar entries for correction, even when the country was invalid.
                $fallback = self::value( $customer, $country_key );
                if ( ! isset( $allowed[ $fallback ] ) ) { $fallback = key( $allowed ); }
                if ( $fallback ) {
                    foreach ( self::fields( $customer, $type, $fallback ) as $key => $field ) {
                        try { $values[ $key ] = self::input_value( $post[ $key ] ?? '', $field ); } catch ( InvalidArgumentException $e ) { /* Render stored value for malformed fields. */ }
                    }
                }
                return self::failure( $type, [ __( 'Please select an allowed country.', 'ishi-latepoint-profile' ) ], $values );
            }
            $address = self::fields( $customer, $type, $country );
            foreach ( $address as $key => $field ) {
                $label = isset( $field['label'] ) ? wp_strip_all_tags( $field['label'] ) : $key;
                try {
                    $value = self::input_value( $post[ $key ] ?? '', $field );
                    if ( ( $field['type'] ?? 'text' ) === 'checkbox' ) { $value = (int) isset( $post[ $key ] ); }
                    $value = $key === $country_key ? $country : apply_filters( 'woocommerce_process_myaccount_field_' . $key, $value );
                    if ( ! is_scalar( $value ) && ! is_array( $value ) ) { throw new InvalidArgumentException( 'Invalid filtered value.' ); }
                    $values[ $key ] = $value;
                    if ( ! empty( $field['required'] ) && empty( $value ) ) {
                        wc_add_notice( sprintf( __( '%s is a required field.', 'woocommerce' ), esc_html( $label ) ), 'error', [ 'id' => $key ] );
                    }
                    if ( ! empty( $value ) ) {
                        foreach ( (array) ( $field['validate'] ?? [] ) as $rule ) {
                            if ( in_array( $rule, [ 'postcode', 'phone', 'email', 'state' ], true ) && ! is_scalar( $value ) ) { throw new InvalidArgumentException( 'Invalid field value.' ); }
                            switch ( $rule ) {
                                case 'postcode':
                                    $value = wc_format_postcode( $value, $country );
                                    if ( ! WC_Validation::is_postcode( $value, $country ) ) {
                                        wc_add_notice( $country === 'IE' ? __( 'Please enter a valid Eircode.', 'woocommerce' ) : __( 'Please enter a valid postcode / ZIP.', 'woocommerce' ), 'error', [ 'id' => $key ] );
                                    }
                                    break;
                                case 'phone':
                                    if ( ! WC_Validation::is_phone( $value, $country ) ) { wc_add_notice( sprintf( __( '%s is not a valid phone number.', 'woocommerce' ), esc_html( $label ) ), 'error', [ 'id' => $key ] ); }
                                    break;
                                case 'email':
                                    $value = strtolower( $value );
                                    if ( ! is_email( $value ) ) { wc_add_notice( sprintf( __( '%s is not a valid email address.', 'woocommerce' ), esc_html( $label ) ), 'error', [ 'id' => $key ] ); }
                                    break;
                            }
                        }
                    }
                    // Unlike the native account handler, explicitly enforce country/state membership on the server.
                    if ( $key === $type . '_state' && $value !== '' ) {
                        $states = WC()->countries->get_states( $country );
                        if ( is_array( $states ) && $states ) {
                            $match = null;
                            foreach ( $states as $code => $name ) {
                                if ( is_scalar( $value ) && ( strcasecmp( (string) $value, (string) $code ) === 0 || strcasecmp( (string) $value, $name ) === 0 ) ) { $match = $code; break; }
                            }
                            if ( $match === null ) { wc_add_notice( __( 'Please select a valid state / province for the country.', 'ishi-latepoint-profile' ), 'error', [ 'id' => $key ] ); }
                            else { $value = $match; }
                        } elseif ( is_array( $states ) ) {
                            $value = '';
                            if ( ! empty( $field['required'] ) ) { wc_add_notice( sprintf( __( '%s is a required field.', 'woocommerce' ), esc_html( $label ) ), 'error', [ 'id' => $key ] ); }
                        }
                    }
                    $values[ $key ] = $value;
                    $setter = 'set_' . $key;
                    if ( is_callable( [ $customer, $setter ] ) ) {
                        if ( is_array( $value ) ) { throw new InvalidArgumentException( 'Invalid core field value.' ); }
                        $customer->$setter( $value );
                    } else {
                        // The verified native fallback for registered custom address fields: WC CRUD metadata.
                        $customer->update_meta_data( $key, $value );
                    }
                } catch ( WC_Data_Exception $e ) {
                    wc_add_notice( sprintf( __( 'Please check %s.', 'ishi-latepoint-profile' ), esc_html( $label ) ), 'error', [ 'id' => $key ] );
                } catch ( InvalidArgumentException $e ) {
                    wc_add_notice( sprintf( __( 'Please check %s.', 'ishi-latepoint-profile' ), esc_html( $label ) ), 'error', [ 'id' => $key ] );
                }
            }
            do_action( 'woocommerce_after_save_address_validation', $user_id, $type, $address, $customer );
            if ( wc_notice_count( 'error' ) ) { return self::failure( $type, self::wc_errors(), $values ); }
            if ( (int) $customer->get_id() !== get_current_user_id() ) { throw new RuntimeException( 'Customer identity changed during validation.' ); }
            foreach ( array_keys( $customer->get_changes() ) as $changed ) {
                if ( $changed !== $type ) { throw new RuntimeException( 'An extension tried to change unrelated customer properties.' ); }
            }
            $expected = [];
            foreach ( $address as $key => $field ) { $expected[ $key ] = self::value( $customer, $key ); }
            $started = true;
            if ( (int) $customer->save() !== (int) $user_id ) { throw new RuntimeException( 'Customer save failed.' ); }
            // A returned customer ID alone does not establish that metadata writes succeeded.
            self::verify_saved( $user_id, $expected );
            do_action( 'woocommerce_customer_save_address', $user_id, $type, $address, $customer );
            if ( wc_notice_count( 'error' ) ) {
                return self::failure( $type, array_merge( [ __( 'The address may already have been saved, but an extension reported an error. Please review it before retrying.', 'ishi-latepoint-profile' ) ], self::wc_errors() ), $values );
            }
            self::verify_saved( $user_id, $expected );
            return [ 'success' => true, 'type' => '', 'errors' => [], 'values' => [] ];
        } catch ( Throwable $e ) {
            return self::failure( $type, [ $started
                ? __( 'The save could not be fully confirmed. Some changes may already be stored. Review the address before trying again.', 'ishi-latepoint-profile' )
                : __( 'The address could not be saved. Please check the fields or contact support.', 'ishi-latepoint-profile' ) ], $values );
        } finally {
            remove_filter( 'wp_redirect', $redirect_guard, PHP_INT_MAX );
            if ( $previous_notices !== null ) { wc_set_notices( $previous_notices ); }
        }
    }

    private static function verify_saved( $user_id, $expected ) {
        clean_user_cache( $user_id );
        wp_cache_delete( $user_id, 'user_meta' );
        $fresh = new WC_Customer( $user_id );
        $fresh->read_meta_data( true );
        if ( (int) $fresh->get_id() !== (int) $user_id ) { throw new RuntimeException( 'Customer reload failed.' ); }
        foreach ( $expected as $key => $value ) {
            $stored = self::value( $fresh, $key );
            $same = is_array( $value ) ? $value === $stored : (string) $value === (string) $stored;
            if ( ! $same ) { throw new RuntimeException( 'Persisted address does not match.' ); }
        }
    }

    private static function wc_errors() {
        $messages = [];
        foreach ( wc_get_notices( 'error' ) as $notice ) { $messages[] = $notice['notice']; }
        return $messages;
    }

    public static function messages( $messages, $success = false ) {
        if ( ! $messages ) { return ''; }
        $html = '<ul class="' . ( $success ? 'woocommerce-message' : 'woocommerce-error' ) . '" role="' . ( $success ? 'status' : 'alert' ) . '">';
        foreach ( $messages as $message ) { $html .= '<li>' . wp_kses_post( $message ) . '</li>'; }
        return $html . '</ul>';
    }

    public static function handle_request() {
        $post = get_post();
        if ( $post && has_shortcode( $post->post_content, self::SHORTCODE ) ) { self::no_cache(); }
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' || ( $_POST['action'] ?? '' ) !== self::ACTION ) { return; }
        self::no_cache();
        self::$response = self::save_submission( $_POST );
        if ( ! self::$response['success'] ) { return; } // Same request renders the edit form with its entered values.
        $token = wp_generate_uuid4();
        set_transient( self::notice_key( $token ), true, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( 'ishi_address_notice', $token, self::base_url() ), 303 );
        exit;
    }

    private static function notice_key( $token ) {
        return 'ishi_addr_' . get_current_user_id() . '_' . substr( wp_hash( wp_get_session_token() ), 0, 16 ) . '_' . $token;
    }

    private static function success_notice() {
        $token = $_GET['ishi_address_notice'] ?? '';
        if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $token ) ) { return ''; }
        $key = self::notice_key( $token );
        if ( ! get_transient( $key ) ) { return ''; }
        delete_transient( $key );
        return self::messages( [ __( 'Address changed successfully.', 'woocommerce' ) ], true );
    }
}

Ishi_WooCommerce_Addresses::boot();
