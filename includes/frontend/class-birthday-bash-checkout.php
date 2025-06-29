<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Checkout
 *
 * Handles checkout page integration for the free plugin.
 */
class BirthdayBash_Checkout {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'add_birthday_fields_to_checkout' ) );
        add_action( 'woocommerce_checkout_update_customer_data', array( $this, 'save_birthday_fields_from_checkout' ), 10, 2 );
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_birthday_fields_checkout' ) );
    }

    /**
     * Add birthday fields to checkout page.
     */
    public function add_birthday_fields_to_checkout() {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $birthday_day   = get_user_meta( $user_id, 'birthday_bash_birthday_day', true );
            $birthday_month = get_user_meta( $user_id, 'birthday_bash_birthday_month', true );
        } else {
            $birthday_day   = WC()->session->get( 'birthday_bash_checkout_day', '' );
            $birthday_month = WC()->session->get( 'birthday_bash_checkout_month', '' );
        }

        $is_mandatory = get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $required_attr = $is_mandatory ? 'required' : '';

        echo '<h3>' . esc_html__( 'Birthday Information', 'birthday-bash' ) . '</h3>';

        woocommerce_form_field(
            'birthday_bash_checkout_day',
            array(
                'type'        => 'number',
                'class'       => array( 'form-row-first' ),
                'label'       => esc_html__( 'Birthday Day', 'birthday-bash' ),
                'placeholder' => esc_html__( 'Day (1-31)', 'birthday-bash' ),
                'required'    => $is_mandatory,
                'min'         => 1,
                'max'         => 31,
            ),
            $birthday_day
        );

        woocommerce_form_field(
            'birthday_bash_checkout_month',
            array(
                'type'        => 'select',
                'class'       => array( 'form-row-last' ),
                'label'       => esc_html__( 'Birthday Month', 'birthday-bash' ),
                'placeholder' => esc_html__( 'Month', 'birthday-bash' ),
                'required'    => $is_mandatory,
                'options'     => BirthdayBash_Helper::get_months_for_select( esc_html__( 'Select Month', 'birthday-bash' ) ),
            ),
            $birthday_month
        );
    }

    /**
     * Validate birthday fields on checkout.
     */
    public function validate_birthday_fields_checkout() {
        // Verify the WooCommerce checkout nonce.
        // The $_POST['_wpnonce'] field is typically present in WooCommerce checkout forms.
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'woocommerce-process_checkout' ) ) {
            wc_add_notice( esc_html__( 'Security check failed. Please try again.', 'birthday-bash' ), 'error' );
            return;
        }

        $is_mandatory = get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $day          = isset( $_POST['birthday_bash_checkout_day'] ) ? absint( $_POST['birthday_bash_checkout_day'] ) : 0;
        $month        = isset( $_POST['birthday_bash_checkout_month'] ) ? absint( $_POST['birthday_bash_checkout_month'] ) : 0;
        $fake_year    = 2024; // A leap year for checkdate validation

        // Validate if mandatory fields are missing or if the date is invalid
        if ( $is_mandatory && ( $day < 1 || $day > 31 || $month < 1 || $month > 12 || ! checkdate( $month, $day, $fake_year ) ) ) {
            wc_add_notice( esc_html__( 'Please enter a valid birthday (day and month).', 'birthday-bash' ), 'error' );
        }
    }

    /**
     * Save birthday fields from checkout to user meta or session.
     *
     * @param int $customer_id The customer ID (0 for guest).
     * @param array $data Checkout data.
     */
    public function save_birthday_fields_from_checkout( $customer_id, $data ) {
        // Verify the WooCommerce checkout nonce.
        // Although this hook runs after process, adding a nonce check here is good practice for custom fields.
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'woocommerce-process_checkout' ) ) {
            // Note: Adding a notice here might be too late, as order processing might be underway.
            // This check is mainly for linter compliance and an extra layer of caution.
            return;
        }

        $day   = isset( $_POST['birthday_bash_checkout_day'] ) ? absint( $_POST['birthday_bash_checkout_day'] ) : 0;
        $month = isset( $_POST['birthday_bash_checkout_month'] ) ? absint( $_POST['birthday_bash_checkout_month'] ) : 0;
        $fake_year = 2024; // A leap year for checkdate validation

        // Only save if valid date is provided or if not mandatory and values are empty.
        $should_save = ( $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && checkdate( $month, $day, $fake_year ) );

        if ( $customer_id > 0 ) {
            // Logged-in user
            if ( $should_save ) {
                update_user_meta( $customer_id, 'birthday_bash_birthday_day', $day );
                update_user_meta( $customer_id, 'birthday_bash_birthday_month', $month );
            } else {
                // If not mandatory (or mandatory but invalid, already handled by validate_birthday_fields_checkout) and values are empty/invalid, remove meta.
                delete_user_meta( $customer_id, 'birthday_bash_birthday_day' );
                delete_user_meta( $customer_id, 'birthday_bash_birthday_month' );
            }
        } else {
            // Guest user - store in session
            // Only store if the date is valid. Otherwise, clear session data.
            if ( $should_save ) {
                WC()->session->set( 'birthday_bash_checkout_day', $day );
                WC()->session->set( 'birthday_bash_checkout_month', $month );
            } else {
                WC()->session->set( 'birthday_bash_checkout_day', '' );
                WC()->session->set( 'birthday_bash_checkout_month', '' );
            }
        }
    }
}