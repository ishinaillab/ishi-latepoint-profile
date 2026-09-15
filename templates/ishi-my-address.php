<?php
/**
 * Standalone address display. WooCommerce owns the data and formatting.
 * Ishi Address Template: 2
 */
defined( 'ABSPATH' ) || exit;
if ( empty( $ishi_address_template ) || ! isset( $get_addresses, $customer_id ) ) { return; }
?>
<p><?php echo wp_kses_post( apply_filters( 'woocommerce_my_account_my_address_description', esc_html__( 'The following addresses will be used on the checkout page by default.', 'woocommerce' ) ) ); ?></p>
<p data-ishi-address-unavailable role="status"><?php esc_html_e( 'Address editing is loading or unavailable. If this message remains, enable JavaScript or contact support.', 'ishi-latepoint-profile' ); ?></p>
<div class="u-columns woocommerce-Addresses col2-set addresses">
    <?php $column = 0; foreach ( $get_addresses as $name => $address_title ) :
        ++$column;
        $address = wc_get_account_formatted_address( $name, $customer_id );
        ?>
        <div class="u-column<?php echo esc_attr( $column ); ?> col-<?php echo esc_attr( $column ); ?> woocommerce-Address">
            <header class="woocommerce-Address-title title">
                <h2><?php echo esc_html( $address_title ); ?></h2>
                <button type="button" disabled data-ishi-address-control data-ishi-address-url="<?php echo esc_url( Ishi_WooCommerce_Addresses::endpoint( $name ) ); ?>" class="edit" data-ishi-address-edit style="background:none;border:0;padding:0;color:inherit;font:inherit;text-decoration:underline;cursor:pointer">
                    <?php printf( $address ? esc_html__( 'Edit %s', 'woocommerce' ) : esc_html__( 'Add %s', 'woocommerce' ), esc_html( $address_title ) ); ?>
                </button>
            </header>
            <address>
                <?php
                echo $address ? wp_kses_post( $address ) : esc_html__( 'You have not set up this type of address yet.', 'woocommerce' );
                do_action( 'woocommerce_my_account_after_my_address', $name );
                ?>
            </address>
        </div>
    <?php endforeach; ?>
</div>
