<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Helper
 *
 * Provides helper functions for the Birthday Bash plugin.
 */
class BirthdayBash_Helper {

    /**
     * Generate a unique coupon code.
     *
     * @return string
     */
    public static function generate_unique_coupon_code() {
        $prefix = get_option( 'birthday_bash_coupon_prefix', 'BIRTHDAY-' );
        $code = strtoupper( uniqid( $prefix ) );
        while ( wc_get_coupon_id_by_code( $code ) ) {
            $code = strtoupper( uniqid( $prefix ) );
        }
        return $code;
    }

    /**
     * Create a WooCommerce birthday coupon.
     *
     * @param string $coupon_code
     * @param int $user_id
     * @return int|bool Coupon ID on success, false on failure.
     */
    public static function create_woocommerce_coupon( $coupon_code, $user_id ) {
        $coupon = new WC_Coupon();
        $coupon->set_code( $coupon_code );
        $coupon->set_description( esc_html__( 'Birthday Coupon', 'birthday-bash' ) );
        $coupon->set_discount_type( get_option( 'birthday_bash_coupon_type', 'fixed_cart' ) );
        $coupon->set_amount( get_option( 'birthday_bash_coupon_amount', 10 ) );
        $coupon->set_usage_limit( 1 );
        $coupon->set_usage_limit_per_user( 1 );
        $coupon->set_individual_use( false ); // Can be stacked with other coupons
        $coupon->set_free_shipping( false );
        $coupon->set_date_expires( strtotime( '+' . get_option( 'birthday_bash_coupon_expiry_days', 14 ) . ' days', current_time( 'timestamp' ) ) );
        $coupon->set_email_restrictions( array( get_userdata( $user_id )->user_email ) );

        // Restrict to logged-in users only by adding a meta (can be used for custom checks)
        $coupon->add_meta_data( '_birthday_bash_coupon_logged_in_only', 'yes' );
        $coupon->add_meta_data( '_birthday_bash_coupon', 'yes' ); // Mark as birthday coupon
        $coupon->add_meta_data( '_birthday_bash_user_id', $user_id ); // Store recipient user ID
        $coupon->add_meta_data( '_birthday_bash_issue_date', current_time( 'mysql' ) );

        $coupon_id = $coupon->save();

        if ( $coupon_id ) {
            // Set coupon type as birthday_bash_coupon for easier identification if needed
            wp_set_object_terms( $coupon_id, 'birthday_bash_coupon', 'shop_coupon_type' );
            return $coupon_id;
        }
        return false;
    }

    /**
     * Get human-readable coupon amount text.
     *
     * @param WC_Coupon $coupon The coupon object.
     * @return string
     */
    public static function get_coupon_amount_text( $coupon ) {
        $amount = $coupon->get_amount();
        $type   = $coupon->get_discount_type();

        switch ( $type ) {
            case 'fixed_cart':
                return wc_price( $amount ) . ' ' . esc_html__( 'fixed discount', 'birthday-bash' );
            case 'percent':
                return $amount . '% ' . esc_html__( 'discount', 'birthday-bash' );
            default:
                return $amount;
        }
    }

    /**
     * Get months for select input.
     *
     * @param string $placeholder Optional placeholder text.
     * @param int $selected_month Optional, the month to be selected.
     * @return string HTML options for the select field.
     */
    public static function get_months_for_select( $placeholder = '', $selected_month = '' ) {
        $output = '';
        if ( ! empty( $placeholder ) ) {
            $output .= '<option value="">' . esc_html( $placeholder ) . '</option>';
        }
        for ( $m = 1; $m <= 12; $m++ ) {
            $output .= sprintf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $m ),
                selected( $selected_month, $m, false ),
                esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 10 ) ) )
            );
        }
        return $output;
    }
}