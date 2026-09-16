<?php
/**
 * Standalone address editor. Variables are prepared by the shortcode controller.
 * Ishi Address Template: 2
 */
defined( 'ABSPATH' ) || exit;
if ( empty( $ishi_address_template ) || ! isset( $load_address, $address, $revision ) || ! Ishi_WooCommerce_Addresses::valid_type( $load_address ) ) { return; }
$page_title = $load_address === 'billing' ? __( 'Billing address', 'woocommerce' ) : __( 'Shipping address', 'woocommerce' );
do_action( 'woocommerce_before_edit_account_address_form' );
?>
<form data-ishi-address-form method="post" data-ishi-address-url="<?php echo esc_url( Ishi_WooCommerce_Addresses::endpoint( $load_address ) ); ?>" novalidate onsubmit="return false;">
    <h2><?php echo wp_kses_post( apply_filters( 'woocommerce_my_account_edit_address_title', $page_title, $load_address ) ); ?></h2>
    <p data-ishi-address-unavailable role="status"><?php esc_html_e( 'Background address saving is loading or unavailable. If this message remains, enable JavaScript or contact support. No changes will be submitted.', 'ishi-latepoint-profile' ); ?></p>
    <fieldset data-ishi-address-ready disabled>
    <div class="woocommerce-address-fields">
        <?php do_action( "woocommerce_before_edit_address_form_{$load_address}" ); ?>
        <div class="woocommerce-address-fields__field-wrapper">
            <?php foreach ( $address as $key => $field ) { woocommerce_form_field( $key, $field, $field['value'] ?? '' ); } ?>
        </div>
        <?php do_action( "woocommerce_after_edit_address_form_{$load_address}" ); ?>
        <p>
            <button type="button" data-ishi-address-save class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="ishi_save_address" value="<?php echo esc_attr( $load_address ); ?>"><?php esc_html_e( 'Save changes', 'ishi-latepoint-profile' ); ?></button>
            <span data-ishi-save-status role="status" aria-live="polite" aria-atomic="true" data-ishi-saving-text="<?php echo esc_attr( __( 'Saving…', 'ishi-latepoint-profile' ) ); ?>"></span>
            <?php wp_nonce_field( Ishi_WooCommerce_Addresses::ACTION . '_' . $load_address, 'ishi_address_nonce', false ); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr( Ishi_WooCommerce_Addresses::ACTION ); ?>" />
            <input type="hidden" name="ishi_address_type" value="<?php echo esc_attr( $load_address ); ?>" />
            <input type="hidden" name="ishi_address_revision" value="<?php echo esc_attr( $revision ); ?>" />
        </p>
    </div>
    </fieldset>
</form>
<?php do_action( 'woocommerce_after_edit_account_address_form' ); ?>
