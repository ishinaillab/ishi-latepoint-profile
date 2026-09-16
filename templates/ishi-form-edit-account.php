<?php
/**
 * Independent LatePoint profile template, rendered by [ishi_latepoint_profile].
 * WooCommerce class names are retained for presentation only.
 * Variables are supplied by Ishi_LatePoint_Profile::render(); never include as a WC template.
 */
defined( 'ABSPATH' ) || exit;
if ( ! isset( $context, $customer, $user, $field_required, $form_id, $id_suffix ) || ! class_exists( 'Ishi_LatePoint_Profile' ) ) {
    return;
}
$required_marker = static function ( $field ) use ( $field_required ) {
    if ( $field_required( $field ) ) {
        echo '&nbsp;<span class="required" aria-hidden="true">*</span>';
    }
};
$button_class = function_exists( 'wp_theme_get_element_class_name' ) ? wp_theme_get_element_class_name( 'button' ) : '';
?>
<?php Ishi_Theme_Compatibility::render_styles(); ?>
<div <?php echo Ishi_Shortcode_UI::root_attributes( 'profile', 'ishi-profile-shortcode' ); ?>>
<div class="woocommerce-MyAccount-content">
    <?php echo Ishi_LatePoint_Profile::notice_html( $notice ); ?>

    <form id="<?php echo esc_attr( $form_id ); ?>" class="woocommerce-EditAccountForm edit-account ishi-edit-account-form" data-ishi-ui-form data-ishi-ui-url="<?php echo esc_url( Ishi_LatePoint_Profile::endpoint() ); ?>" method="post" novalidate onsubmit="return false;">

        <p data-ishi-ui-unavailable role="status"><?php esc_html_e( 'Profile editing requires JavaScript. If this message remains, refresh the page or contact support.', 'ishi-latepoint-profile' ); ?></p>

        <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
            <label for="account_first_name<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'First name', 'ishi-latepoint-profile' ); ?><?php $required_marker( 'first_name' ); ?></label>
            <input data-ishi-ui-ready disabled type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name<?php echo esc_attr( $id_suffix ); ?>" autocomplete="given-name" value="<?php echo esc_attr( $customer->first_name ); ?>" aria-required="<?php echo $field_required( 'first_name' ) ? 'true' : 'false'; ?>" maxlength="255" />
        </p>
        <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
            <label for="account_last_name<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Last name', 'ishi-latepoint-profile' ); ?><?php $required_marker( 'last_name' ); ?></label>
            <input data-ishi-ui-ready disabled type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name<?php echo esc_attr( $id_suffix ); ?>" autocomplete="family-name" value="<?php echo esc_attr( $customer->last_name ); ?>" aria-required="<?php echo $field_required( 'last_name' ) ? 'true' : 'false'; ?>" maxlength="255" />
        </p>
        <div class="clear"></div>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_display_name<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Display name', 'ishi-latepoint-profile' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
            <input data-ishi-ui-ready disabled type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name<?php echo esc_attr( $id_suffix ); ?>" aria-describedby="account_display_name_description<?php echo esc_attr( $id_suffix ); ?>" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" maxlength="250" />
            <span id="account_display_name_description<?php echo esc_attr( $id_suffix ); ?>"><em><?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews', 'ishi-latepoint-profile' ); ?></em></span>
        </p>
        <div class="clear"></div>

        <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
            <label for="account_phone<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Phone number', 'ishi-latepoint-profile' ); ?><?php $required_marker( 'phone' ); ?></label>
            <input data-ishi-ui-ready disabled type="tel" class="woocommerce-Input woocommerce-Input--phone input-text" name="account_phone" id="account_phone<?php echo esc_attr( $id_suffix ); ?>" autocomplete="tel" value="<?php echo esc_attr( $customer->phone ); ?>" aria-required="<?php echo $field_required( 'phone' ) ? 'true' : 'false'; ?>" maxlength="255" />
        </p>
        <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
            <label for="account_email<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Email address', 'ishi-latepoint-profile' ); ?><?php $required_marker( 'email' ); ?></label>
            <input data-ishi-ui-ready disabled type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email<?php echo esc_attr( $id_suffix ); ?>" autocomplete="email" value="<?php echo esc_attr( $customer->email ); ?>" aria-required="<?php echo $field_required( 'email' ) ? 'true' : 'false'; ?>" maxlength="100" />
        </p>

        <fieldset>
            <legend><?php esc_html_e( 'Password change', 'ishi-latepoint-profile' ); ?></legend>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="password_current<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Current password (leave blank to leave unchanged)', 'ishi-latepoint-profile' ); ?></label>
                <input data-ishi-ui-ready disabled type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current<?php echo esc_attr( $id_suffix ); ?>" autocomplete="current-password" />
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="password_1<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'New password (leave blank to leave unchanged)', 'ishi-latepoint-profile' ); ?></label>
                <input data-ishi-ui-ready disabled type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1<?php echo esc_attr( $id_suffix ); ?>" autocomplete="new-password" aria-describedby="password_help<?php echo esc_attr( $id_suffix ); ?>" />
                <span id="password_help<?php echo esc_attr( $id_suffix ); ?>"><em><?php esc_html_e( 'Use at least 15 characters. Enter the current password for your customer sign-in.', 'ishi-latepoint-profile' ); ?></em></span>
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="password_2<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Confirm new password', 'ishi-latepoint-profile' ); ?></label>
                <input data-ishi-ui-ready disabled type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2<?php echo esc_attr( $id_suffix ); ?>" autocomplete="new-password" />
            </p>
        </fieldset>
        <div class="clear"></div>

        <p>
            <?php wp_nonce_field( Ishi_LatePoint_Profile::nonce_action( $context ), 'ishi_lp_profile_nonce', false ); ?>
            <button type="button" data-ishi-ui-save data-ishi-ui-ready disabled class="woocommerce-Button button<?php echo esc_attr( $button_class ? ' ' . $button_class : '' ); ?>" name="ishi_lp_submit" value="1"><?php esc_html_e( 'Save changes', 'ishi-latepoint-profile' ); ?></button>
            <input type="hidden" name="action" value="<?php echo esc_attr( Ishi_LatePoint_Profile::ACTION ); ?>" />
            <input type="hidden" name="ishi_lp_revision" value="<?php echo esc_attr( Ishi_LatePoint_Profile::revision( $context ) ); ?>" />
        </p>
    </form>
</div>
</div>
