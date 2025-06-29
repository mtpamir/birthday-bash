<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Frontend
 *
 * Handles frontend functionalities for the free plugin.
 */
class BirthdayBash_Frontend {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action( 'woocommerce_before_cart', array( $this, 'display_active_birthday_coupon_on_cart' ) );
    }

    /**
     * Display active birthday coupon on cart page.
     */
    public function display_active_birthday_coupon_on_cart() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $issued_coupons = BirthdayBash_DB::get_user_issued_birthday_coupons( $user_id );

        if ( ! empty( $issued_coupons ) ) {
            foreach ( $issued_coupons as $coupon_data ) {
                $coupon_code = $coupon_data->coupon_code;
                $coupon_obj  = new WC_Coupon( $coupon_code );

                if ( $coupon_obj->is_valid() && ! $coupon_obj->is_used_by( $user_id ) && ! $coupon_obj->is_expired() ) {
                    $coupon_amount_text = BirthdayBash_Helper::get_coupon_amount_text( $coupon_obj );
                    wc_print_notice(
                        sprintf(
                            /* translators: %1$s: coupon amount, %2$s: coupon code */
                            esc_html__( 'You have an active birthday coupon: %1$s. Use code: %2$s', 'birthday-bash' ),
                            '<strong>' . $coupon_amount_text . '</strong>',
                            '<strong>' . esc_html( $coupon_code ) . '</strong>'
                        ),
                        'success'
                    );
                    return; // Display only one active coupon for simplicity in free version
                }
            }
        }
    }
}