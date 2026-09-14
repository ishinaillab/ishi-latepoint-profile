<?php
/**
 * Standalone address display. WooCommerce owns the data and formatting.
 * Ishi Address Template: 1
 */
defined( 'ABSPATH' ) || exit;
if ( empty( $ishi_address_template ) || ! isset( $get_addresses, $customer_id, $base_url ) ) { return; }
?>
<p><?php echo wp_kses_post( apply_filters( 'woocommerce_my_account_my_address_description', esc_html__( 'The following addresses will be used on the checkout page by default.', 'woocommerce' ) ) ); ?></p>
<div class="u-columns woocommerce-Addresses col2-set addresses">
    <?php $column = 0; foreach ( $get_addresses as $name => $address_title ) :
        ++$column;
        $address = wc_get_account_formatted_address( $name, $customer_id );
        ?>
        <div class="u-column<?php echo esc_attr( $column ); ?> col-<?php echo esc_attr( $column ); ?> woocommerce-Address">
            <header class="woocommerce-Address-title title">
                <h2><?php echo esc_html( $address_title ); ?></h2>
                <a href="<?php echo esc_url( add_query_arg( Ishi_WooCommerce_Addresses::MODE, $name, $base_url ) ); ?>" class="edit">
                    <?php printf( $address ? esc_html__( 'Edit %s', 'woocommerce' ) : esc_html__( 'Add %s', 'woocommerce' ), esc_html( $address_title ) ); ?>
                </a>
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
