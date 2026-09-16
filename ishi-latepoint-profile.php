<?php
/**
 * Plugin Name: Ishi LatePoint Profile
 * Description: Independent customer profile shortcode using the child theme's existing form design.
 * Version: 1.3.10
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: ishi-latepoint-profile
 */

defined( 'ABSPATH' ) || exit;

final class Ishi_LatePoint_Profile {
    private static $render_count = 0;
    const ACTION = 'ishi_lp_save_profile';
    const SHORTCODE = 'ishi_latepoint_profile';
    const TEMPLATE = '/templates/ishi-form-edit-account.php';
    const FIELD_MAP = [
        'account_first_name' => 'first_name',
        'account_last_name'  => 'last_name',
        'account_phone'      => 'phone',
        'account_email'      => 'email',
    ];

    public static function boot() {
        add_shortcode( self::SHORTCODE, [ __CLASS__, 'render' ] );
        add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_post' ] );
        add_action( 'admin_post_nopriv_' . self::ACTION, [ __CLASS__, 'handle_post' ] );
        add_action( 'template_redirect', [ __CLASS__, 'protect_shortcode_page' ] );
    }

    public static function protect_shortcode_page() {
        $post = get_post();
        if ( $post && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
            self::no_cache();
        }
    }

    private static function no_cache() {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        if ( ! headers_sent() ) {
            nocache_headers();
        }
    }

    /**
     * Resolve only an existing, unambiguous, owned link. Never match accounts by email.
     * Core 5.6.11 customer_helper.php:162-203 has side effects during resolution:
     * it can copy WP email into LatePoint or create/relink an account. Do not call it here.
     */
    public static function context() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'login', __( 'Please sign in to edit your profile.', 'ishi-latepoint-profile' ) );
        }
        $required = [
            'OsCustomerModel' => [ 'where', 'set_limit', 'get_results_as_models', 'is_new_record', 'set_data', 'save', 'validate', 'get_data_vars', 'get_error_messages', 'update_password' ],
            'OsAuthHelper' => [ 'is_customer_auth_disabled', 'can_wp_users_login_as_customers', 'get_logged_in_customer_id', 'verify_password' ],
            'OsCustomerHelper' => [ 'is_wp_user_safe_for_customer_link' ],
            'OsSettingsHelper' => [ 'get_default_fields_for_customer' ],
        ];
        foreach ( $required as $class => $methods ) {
            foreach ( $methods as $method ) {
                if ( ! is_callable( [ $class, $method ] ) && ! method_exists( $class, $method ) ) {
                    return new WP_Error( 'unavailable', __( 'Profile editing is currently unavailable. Please contact support.', 'ishi-latepoint-profile' ) );
                }
            }
        }
        if ( ! defined( 'LATEPOINT_PARAMS_SCOPE_CUSTOMER' ) || OsAuthHelper::is_customer_auth_disabled() ) {
            return new WP_Error( 'disabled', __( 'Customer sign-in must be enabled before editing this profile.', 'ishi-latepoint-profile' ) );
        }
        $user = get_userdata( get_current_user_id() );
        if ( ! $user || ! OsCustomerHelper::is_wp_user_safe_for_customer_link( (int) $user->ID ) ) {
            return new WP_Error( 'role', __( 'This form is available to customer accounts only.', 'ishi-latepoint-profile' ) );
        }
        $query = new OsCustomerModel();
        $matches = $query->where( [ 'wordpress_user_id' => (int) $user->ID ] )->set_limit( 2 )->get_results_as_models();
        if ( ! is_array( $matches ) || count( $matches ) !== 1 ) {
            return new WP_Error( 'link', __( 'Your account does not have a unique connected customer profile. Please contact support.', 'ishi-latepoint-profile' ) );
        }
        $customer = reset( $matches );
        if ( ! $customer instanceof OsCustomerModel || $customer->is_new_record() || (int) $customer->wordpress_user_id !== (int) $user->ID ) {
            return new WP_Error( 'owner', __( 'The connected customer profile could not be verified.', 'ishi-latepoint-profile' ) );
        }
        $wp_auth = OsAuthHelper::can_wp_users_login_as_customers();
        // In local-auth mode, a WP session alone does not authenticate a LatePoint customer.
        if ( ! $wp_auth && (int) OsAuthHelper::get_logged_in_customer_id() !== (int) $customer->id ) {
            return new WP_Error( 'customer_login', __( 'Please also sign in to the connected customer account before editing this profile.', 'ishi-latepoint-profile' ) );
        }
        if ( ! $wp_auth ) {
            // Core 5.6.11 verifies the cookie HMAC but omits an explicit expiry comparison.
            // Add that comparison after native authentication; never authorize from the cookie alone.
            if ( ! is_callable( [ 'OsSessionsHelper', 'get_customer_session_cookie' ] ) || ! class_exists( 'OsSessionModel' ) ) {
                return new WP_Error( 'session', __( 'Customer session verification is unavailable.', 'ishi-latepoint-profile' ) );
            }
            $parts = explode( '||', (string) OsSessionsHelper::get_customer_session_cookie() );
            if ( count( $parts ) !== 3 || ! ctype_digit( $parts[0] ) || ! ctype_digit( $parts[1] ) || (int) $parts[1] <= time() ) {
                return new WP_Error( 'session_expired', __( 'Please sign in to your customer account again.', 'ishi-latepoint-profile' ) );
            }
            $session = new OsSessionModel( (int) $parts[0] );
            if ( $session->is_new_record() || (int) $session->session_key !== (int) $customer->id || (int) $session->expiration <= time() ) {
                return new WP_Error( 'session_expired', __( 'Please sign in to your customer account again.', 'ishi-latepoint-profile' ) );
            }
        }
        return [ 'user' => $user, 'customer' => $customer, 'wp_auth' => $wp_auth ];
    }

    public static function nonce_action( $context ) {
        return self::ACTION . '_' . (int) $context['customer']->id;
    }

    /** Signed revision prevents an already-stale form from overwriting more recent edits. */
    public static function revision( $context ) {
        $c = $context['customer'];
        $u = $context['user'];
        return wp_hash( wp_json_encode( [
            $c->id, $c->wordpress_user_id, $c->first_name, $c->last_name, $c->phone, $c->email,
            $c->password, $c->updated_at, $c->custom_fields ?? [],
            $u->ID, $u->display_name, $u->first_name, $u->last_name, $u->user_email, $u->user_pass,
            $context['wp_auth'],
        ] ) );
    }

    public static function render( $atts = [], $content = null, $tag = '' ) {
        self::no_cache();
        $buffer_level = ob_get_level();
        try {
            $context = self::context();
            if ( is_wp_error( $context ) ) {
                return self::notice_html( [ 'success' => false, 'messages' => $context->get_error_messages() ] );
            }
            $template = __DIR__ . self::TEMPLATE;
            if ( ! is_readable( $template ) ) {
                return self::notice_html( [ 'success' => false, 'messages' => [ __( 'The profile form is unavailable. Please contact support.', 'ishi-latepoint-profile' ) ] ] );
            }
            $customer = $context['customer'];
            $user = $context['user'];
            $required_fields = OsSettingsHelper::get_default_fields_for_customer();
            $field_required = static function ( $name ) use ( $required_fields, $context ) {
                return ( $name === 'email' && $context['wp_auth'] ) ||
                    ( ! empty( $required_fields[ $name ]['active'] ) && ! empty( $required_fields[ $name ]['required'] ) );
            };
            $form_id = wp_unique_id( 'ishi-profile-' );
            $id_suffix = ++self::$render_count === 1 ? '' : '-' . $form_id;
            $return_url = self::return_url();
            $notice = self::consume_notice();
            // Styling only. No WooCommerce PHP API, form hook, route or customer object is used.
            foreach ( [ 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen' ] as $handle ) {
                if ( wp_style_is( $handle, 'registered' ) ) {
                    wp_enqueue_style( $handle );
                }
            }
            ob_start();
            include $template;
            return ob_get_clean();
        } catch ( Throwable $e ) {
            while ( ob_get_level() > $buffer_level ) {
                ob_end_clean();
            }
            return self::notice_html( [ 'success' => false, 'messages' => [ __( 'The profile could not be loaded. Please contact support.', 'ishi-latepoint-profile' ) ] ] );
        }
    }

    /** Only explicitly named scalar fields are accepted. Never accept model IDs or arbitrary model data. */
    private static function read_input( $post ) {
        $data = [];
        $names = array_merge( array_keys( self::FIELD_MAP ), [ 'account_display_name', 'password_current', 'password_1', 'password_2', 'ishi_lp_profile_nonce', 'ishi_lp_revision' ] );
        foreach ( $names as $name ) {
            if ( array_key_exists( $name, $post ) ) {
                if ( ! is_string( $post[ $name ] ) || strlen( $post[ $name ] ) > 4096 || strpos( $post[ $name ], "\0" ) !== false ) {
                    return new WP_Error( 'input', __( 'The form contains an invalid field. Please reload and try again.', 'ishi-latepoint-profile' ) );
                }
                $data[ $name ] = $post[ $name ];
            }
        }
        return $data;
    }

    private static function length( $value ) {
        return preg_match_all( '/./us', $value, $matches );
    }

    private static function result( $success, $messages, $partial = false ) {
        if ( $partial ) {
            array_unshift( $messages, __( 'Not all changes were saved. Some changes may already have been applied. The form now shows the stored values; review them before trying again.', 'ishi-latepoint-profile' ) );
        }
        return [ 'success' => $success, 'messages' => $messages ];
    }

    private static function refresh_user( $id ) {
        clean_user_cache( $id );
        // Core LP's lookup reads wp_get_current_user(); do not leave a stale email in that object.
        wp_set_current_user( 0 );
        return wp_set_current_user( $id );
    }

    /**
     * Submission service. $post is wp_unslash($_POST); it is not native LatePoint's params envelope.
     * Validation precedes writes. Cross-system writes are not transactional; every result is verified.
     */
    public static function save_submission( $post ) {
        $started = false;
        try {
            if ( ! is_array( $post ) ) {
                return self::result( false, [ __( 'Invalid form submission.', 'ishi-latepoint-profile' ) ] );
            }
            $context = self::context();
            if ( is_wp_error( $context ) ) {
                return self::result( false, $context->get_error_messages() );
            }
            $data = self::read_input( $post );
            if ( is_wp_error( $data ) ) {
                return self::result( false, $data->get_error_messages() );
            }
            if ( ! wp_verify_nonce( $data['ishi_lp_profile_nonce'] ?? '', self::nonce_action( $context ) ) ) {
                return self::result( false, [ __( 'This form has expired. Reload the page and try again.', 'ishi-latepoint-profile' ) ] );
            }
            // This template has no upload controls. Pro's save hook reads global files,
            // so reject unexpected attachments instead of letting that hook process them.
            if ( ! empty( $_FILES ) ) {
                return self::result( false, [ __( 'This profile form does not accept file uploads.', 'ishi-latepoint-profile' ) ] );
            }
            if ( ! hash_equals( self::revision( $context ), $data['ishi_lp_revision'] ?? '' ) ) {
                return self::result( false, [ __( 'Your account changed after this form was opened. Review the current values and try again.', 'ishi-latepoint-profile' ) ] );
            }
            $customer = $context['customer'];
            $user = $context['user'];
            $errors = [];
            $profile = [];
            foreach ( self::FIELD_MAP as $input => $property ) {
                if ( array_key_exists( $input, $data ) ) {
                    $profile[ $property ] = $data[ $input ];
                }
            }
            $display = array_key_exists( 'account_display_name', $data ) ? sanitize_text_field( $data['account_display_name'] ) : null;
            if ( $display !== null && ( trim( $display ) === '' || self::length( $display ) > 250 ) ) {
                $errors[] = __( 'Enter a display name of 1 to 250 characters.', 'ishi-latepoint-profile' );
            }
            if ( isset( $profile['email'] ) ) {
                $email = trim( $profile['email'] );
                $clean_email = sanitize_email( $email );
                if ( $email !== '' && ( $email !== $clean_email || ! is_email( $email ) || strlen( $email ) > 100 ) ) {
                    $errors[] = __( 'Enter a valid email address of no more than 100 characters.', 'ishi-latepoint-profile' );
                }
                if ( $email === '' && $context['wp_auth'] ) {
                    $errors[] = __( 'An email address is required for your connected sign-in account.', 'ishi-latepoint-profile' );
                }
                // Native sync affects WP email too; detect its conflicts before LP is written.
                $other_user = $email !== '' ? email_exists( $email ) : false;
                if ( $other_user && (int) $other_user !== (int) $user->ID ) {
                    $errors[] = __( 'That email address is already in use. Please choose another.', 'ishi-latepoint-profile' );
                }
                $profile['email'] = $email;
            }
            $old_customer_data = $customer->get_data_vars();
            if ( $profile ) {
                // Core customer_model.php:386-400 and model.php:558-607 sanitize and run set-data hooks.
                $customer->set_data( $profile, LATEPOINT_PARAMS_SCOPE_CUSTOMER );
                foreach ( [ 'first_name', 'last_name', 'phone' ] as $field ) {
                    if ( self::length( (string) $customer->$field ) > 255 ) {
                        $errors[] = __( 'Names and phone number must each be no more than 255 characters.', 'ishi-latepoint-profile' );
                        break;
                    }
                }
                // Normal native rules, including Pro's model validation. Never skip validation.
                if ( ! $customer->validate() ) {
                    $errors = array_merge( $errors, $customer->get_error_messages( 'validation' ) ?: [ __( 'Please check the customer fields.', 'ishi-latepoint-profile' ) ] );
                }
            }
            $current = $data['password_current'] ?? '';
            $new = $data['password_1'] ?? '';
            $confirm = $data['password_2'] ?? '';
            $change_password = $current !== '' || $new !== '' || $confirm !== '';
            $rate_key = 'ishi_lp_pw_' . (int) $user->ID;
            if ( $change_password ) {
                $length = self::length( $new );
                if ( $length === false || $length < 15 || trim( $new ) === '' ) {
                    $errors[] = __( 'Use a new password with at least 15 characters.', 'ishi-latepoint-profile' );
                }
                if ( $new !== $confirm ) {
                    $errors[] = __( 'The new passwords do not match.', 'ishi-latepoint-profile' );
                }
                $attempts = (int) get_transient( $rate_key );
                if ( $attempts >= 5 ) {
                    $errors[] = __( 'Too many incorrect password attempts. Wait 15 minutes before trying again.', 'ishi-latepoint-profile' );
                } else {
                    $correct = $current !== '' && ( $context['wp_auth']
                        ? wp_check_password( $current, $user->user_pass, $user->ID )
                        : OsAuthHelper::verify_password( $current, $customer->password ) );
                    if ( ! $correct ) {
                        set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
                        $errors[] = __( 'The current password is incorrect.', 'ishi-latepoint-profile' );
                    }
                }
            }
            if ( $errors ) {
                return self::result( false, array_unique( $errors ) );
            }
            $expected_customer = clone $customer;
            $expected_wp = [];
            if ( $profile ) {
                foreach ( [ 'first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'user_email' ] as $lp => $wp ) {
                    $expected_wp[ $wp ] = ! empty( $customer->$lp ) ? (string) $customer->$lp : (string) $user->$wp;
                }
            }

            if ( $profile ) {
                // Save unchanged fields as native Profile does, retaining core/Pro save callbacks.
                $started = true;
                if ( ! $customer->save() ) {
                    return self::result( false, [ __( 'The customer profile could not be saved.', 'ishi-latepoint-profile' ) ], true );
                }
                $fresh_user = self::refresh_user( (int) $user->ID );
                $fresh = new OsCustomerModel( $customer->id );
                // Exactly the native controller event, with the pre-save data array. No extra password event.
                do_action( 'latepoint_customer_updated', $fresh, $old_customer_data );
                $fresh = new OsCustomerModel( $customer->id );
                $fresh_user = self::refresh_user( (int) $user->ID );
                if ( ! self::profile_matches( $fresh, $expected_customer ) ) {
                    return self::result( false, [ __( 'The saved customer profile could not be confirmed.', 'ishi-latepoint-profile' ) ], true );
                }
                // Native model-save sync copies only NONEMPTY name/email values to WP.
                foreach ( $expected_wp as $wp => $expected ) {
                    if ( (string) $fresh_user->$wp !== $expected ) {
                        return self::result( false, [ __( 'The connected account did not finish synchronizing. Please contact support before retrying.', 'ishi-latepoint-profile' ) ], true );
                    }
                }
            }
            if ( $display !== null && $display !== (string) $user->display_name ) {
                $started = true;
                $updated = wp_update_user( wp_slash( [ 'ID' => (int) $user->ID, 'display_name' => $display ] ) );
                if ( is_wp_error( $updated ) || (int) $updated !== (int) $user->ID ) {
                    return self::result( false, [ __( 'The display name could not be saved.', 'ishi-latepoint-profile' ) ], true );
                }
                self::refresh_user( (int) $user->ID );
            }
            if ( $change_password ) {
                $started = true;
                // Core customer_model.php:315-343 hashes plaintext and conditionally updates WP.
                if ( ! $customer->update_password( $new ) ) {
                    return self::result( false, [ __( 'The password update did not complete. Check which password works before trying again.', 'ishi-latepoint-profile' ) ], true );
                }
                // Core reuses a pre-password WP_User when refreshing caches. Reload after its call.
                $fresh_user = self::refresh_user( (int) $user->ID );
                $fresh = new OsCustomerModel( $customer->id );
                if ( ! OsAuthHelper::verify_password( $new, $fresh->password ) ||
                    ( $context['wp_auth'] && ! wp_check_password( $new, $fresh_user->user_pass, $user->ID ) ) ) {
                    return self::result( false, [ __( 'The password update could not be confirmed in every required account. Please contact support.', 'ishi-latepoint-profile' ) ], true );
                }
                delete_transient( $rate_key );
            }
            // Reload both stores after ALL callbacks. Do not resolve through LP's mutating WP lookup.
            $final_user = self::refresh_user( (int) $user->ID );
            $final_customer = new OsCustomerModel( $customer->id );
            if ( ! self::profile_matches( $final_customer, $expected_customer ) ||
                ( $display !== null && (string) $final_user->display_name !== $display ) ) {
                return self::result( false, [ __( 'The saved values could not all be confirmed. Please contact support.', 'ishi-latepoint-profile' ) ], $started );
            }
            foreach ( $expected_wp as $wp => $expected ) {
                if ( (string) $final_user->$wp !== $expected ) {
                    return self::result( false, [ __( 'The connected account values changed during the update. Please contact support.', 'ishi-latepoint-profile' ) ], $started );
                }
            }
            return self::result( true, [ __( 'Your changes have been saved.', 'ishi-latepoint-profile' ) ] );
        } catch ( Throwable $e ) {
            // Never expose SQL errors, traces, password data or plugin internals to the browser.
            return self::result( false, [ __( 'The update could not be completed. Please contact support.', 'ishi-latepoint-profile' ) ], $started );
        }
    }

    private static function profile_matches( $actual, $expected ) {
        if ( $actual->is_new_record() || (int) $actual->wordpress_user_id !== (int) $expected->wordpress_user_id ) {
            return false;
        }
        foreach ( [ 'first_name', 'last_name', 'phone', 'email' ] as $field ) {
            if ( (string) $actual->$field !== (string) $expected->$field ) {
                return false;
            }
        }
        return true;
    }

    public static function handle_post() {
        self::no_cache();
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
            wp_die( esc_html__( 'Please submit the profile form.', 'ishi-latepoint-profile' ), '', [ 'response' => 405 ] );
        }
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Your sign-in session has expired. Sign in and try again.', 'ishi-latepoint-profile' ), '', [ 'response' => 403 ] );
        }
        $post = wp_unslash( $_POST );
        $result = self::save_submission( $post );
        $token = wp_generate_uuid4();
        if ( ! set_transient( self::notice_key( $token ), $result, 5 * MINUTE_IN_SECONDS ) ) {
            // Do not silently lose the outcome if the flash store is unavailable.
            wp_die( self::notice_html( $result ), '', [ 'response' => $result['success'] ? 200 : 400, 'back_link' => true ] );
        }
        $destination = self::return_url( isset( $post['ishi_lp_return'] ) && is_string( $post['ishi_lp_return'] ) ? $post['ishi_lp_return'] : '' );
        wp_safe_redirect( add_query_arg( 'ishi_lp_notice', $token, $destination ), 303 );
        exit;
    }

    public static function return_url( $candidate = '' ) {
        if ( $candidate === '' ) {
            $candidate = wp_get_referer() ?: home_url( '/' );
            if ( ! is_admin() && isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
                $origin = wp_parse_url( home_url() );
                $candidate = $origin['scheme'] . '://' . $origin['host'] . ( isset( $origin['port'] ) ? ':' . $origin['port'] : '' ) . wp_unslash( $_SERVER['REQUEST_URI'] );
            }
        }
        $candidate = wp_validate_redirect( $candidate, home_url( '/' ) );
        // Stay on this exact site's origin even if another plugin allows external redirect hosts.
        $home = wp_parse_url( home_url( '/' ) );
        $url = wp_parse_url( $candidate );
        if ( ! is_array( $url ) || ( $url['host'] ?? '' ) !== ( $home['host'] ?? '' ) ||
            ( $url['scheme'] ?? '' ) !== ( $home['scheme'] ?? '' ) || ( $url['port'] ?? null ) !== ( $home['port'] ?? null ) ) {
            $candidate = home_url( '/' );
        }
        return remove_query_arg( [ 'ishi_lp_notice' ], $candidate );
    }

    private static function notice_key( $token ) {
        return 'ishi_lp_' . get_current_user_id() . '_' . substr( wp_hash( wp_get_session_token() ), 0, 16 ) . '_' . $token;
    }

    private static function consume_notice() {
        $token = isset( $_GET['ishi_lp_notice'] ) && is_string( $_GET['ishi_lp_notice'] ) ? wp_unslash( $_GET['ishi_lp_notice'] ) : '';
        if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $token ) ) {
            return null;
        }
        $key = self::notice_key( $token );
        $notice = get_transient( $key );
        delete_transient( $key );
        return is_array( $notice ) ? $notice : null;
    }

    public static function notice_html( $notice ) {
        if ( ! $notice ) {
            return '';
        }
        $success = ! empty( $notice['success'] );
        $html = '<div class="woocommerce"><ul class="' . ( $success ? 'woocommerce-message' : 'woocommerce-error' ) . '" role="' . ( $success ? 'status' : 'alert' ) . '">';
        foreach ( $notice['messages'] as $message ) {
            $html .= '<li>' . esc_html( wp_strip_all_tags( (string) $message ) ) . '</li>';
        }
        return $html . '</ul></div>';
    }
}

require_once __DIR__ . '/includes/class-ishi-theme-compatibility.php';

Ishi_LatePoint_Profile::boot();

// This independent module owns WooCommerce addresses; it does not use LatePoint customer data.
require_once __DIR__ . '/includes/class-ishi-woocommerce-addresses.php';
