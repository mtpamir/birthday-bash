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

        add_action( 'woocommerce_checkout_update_user_meta', array( $this, 'save_birthday_fields_to_user_meta' ), 10, 2 );

        add_action( 'woocommerce_checkout_update_customer_data', array( $this, 'save_birthday_fields_from_checkout' ), 10, 2 );

        add_action( 'woocommerce_checkout_process', array( $this, 'validate_birthday_fields_checkout' ) );

        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_guest_birthday_to_order' ), 10, 2 );
    }

    public function add_birthday_fields_to_checkout() {
        $user_id        = get_current_user_id();
        $birthday_day   = '';
        $birthday_month = '';

        if ( is_user_logged_in() ) {
            $birthday_day   = get_user_meta( $user_id, 'birthday_bash_birthday_day', true );
            $birthday_month = get_user_meta( $user_id, 'birthday_bash_birthday_month', true );
        } else {
            if ( WC()->session && method_exists( WC()->session, 'get' ) ) {
                $birthday_day   = WC()->session->get( 'birthday_bash_checkout_day', '' );
                $birthday_month = WC()->session->get( 'birthday_bash_checkout_month', '' );
            }
        }

        $is_mandatory = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );

        echo '<div id="birthday-bash-checkout-fields">';
        echo '<h3>' . esc_html__( 'Birthday Information', 'birthday-bash' ) . '</h3>';

        wp_nonce_field( 'birthday_bash_checkout_action', 'birthday_bash_checkout_nonce_field' );

        woocommerce_form_field(
            'birthday_bash_checkout_day',
            array(
                'type'              => 'number',
                'class'             => array( 'form-row-first' ),
                'label'             => esc_html__( 'Birthday Day', 'birthday-bash' ),
                'placeholder'       => esc_html__( 'Day (1-31)', 'birthday-bash' ),
                'required'          => $is_mandatory,
                'min'               => 1,
                'max'               => 31,
                'input_class'       => array( 'woocommerce-Input', 'woocommerce-Input--text', 'input-text' ),
                'custom_attributes' => array( 'autocomplete' => 'bday-day' ),
            ),
            $birthday_day
        );

        woocommerce_form_field(
            'birthday_bash_checkout_month',
            array(
                'type'              => 'select',
                'class'             => array( 'form-row-last' ),
                'label'             => esc_html__( 'Birthday Month', 'birthday-bash' ),
                'required'          => $is_mandatory,
                'options'           => BirthdayBash_Helper::get_months_for_select( esc_html__( 'Select Month', 'birthday-bash' ) ),
                'input_class'       => array( 'woocommerce-Input', 'woocommerce-Input--select' ),
                'custom_attributes' => array( 'autocomplete' => 'bday-month' ),
            ),
            $birthday_month
        );
        echo '</div>';
    }

    public function validate_birthday_fields_checkout() {
        $is_mandatory = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );

        $nonce = isset( $_POST['birthday_bash_checkout_nonce_field'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday_bash_checkout_nonce_field'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'birthday_bash_checkout_action' ) ) {
            wc_add_notice( esc_html__( 'Security check failed. Please try again.', 'birthday-bash' ), 'error' );
            return;
        }

        $day          = isset( $_POST['birthday_bash_checkout_day'] ) ? absint( $_POST['birthday_bash_checkout_day'] ) : 0;
        $month        = isset( $_POST['birthday_bash_checkout_month'] ) ? absint( $_POST['birthday_bash_checkout_month'] ) : 0;
        $fake_year    = 2024;

        $day_has_value   = ( $day > 0 );
        $month_has_value = ( $month > 0 );

        if ( $is_mandatory ) {
            if ( ! $day_has_value || ! $month_has_value || ! checkdate( $month, $day, $fake_year ) ) {
                wc_add_notice( esc_html__( 'Please enter a valid birthday. Both day and month are required and must form a correct date.', 'birthday-bash' ), 'error' );
            }
        } else {
            if ( ( $day_has_value || $month_has_value ) && ! checkdate( $month, $day, $fake_year ) ) {
                wc_add_notice( esc_html__( 'The birthday date you provided is invalid. Please correct it or leave both fields empty.', 'birthday-bash' ), 'error' );
            }
        }
    }

    public function save_birthday_fields_to_user_meta( $user_id, $data ) {
        $nonce = isset( $_POST['birthday_bash_checkout_nonce_field'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday_bash_checkout_nonce_field'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'birthday_bash_checkout_action' ) ) {
            return;
        }

        $is_mandatory = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $day          = isset( $_POST['birthday_bash_checkout_day'] ) ? absint( $_POST['birthday_bash_checkout_day'] ) : 0;
        $month        = isset( $_POST['birthday_bash_checkout_month'] ) ? absint( $_POST['birthday_bash_checkout_month'] ) : 0;
        $fake_year    = 2024;

        $is_valid_date = checkdate( $month, $day, $fake_year );

        if ( $is_valid_date ) {
            update_user_meta( $user_id, 'birthday_bash_birthday_day', $day );
            update_user_meta( $user_id, 'birthday_bash_birthday_month', $month );
        } elseif ( ! $is_mandatory && ( 0 === $day && 0 === $month ) ) {
            delete_user_meta( $user_id, 'birthday_bash_birthday_day' );
            delete_user_meta( $user_id, 'birthday_bash_birthday_month' );
        }
    }

    public function save_birthday_fields_from_checkout( $customer_id, $data ) {
        if ( $customer_id > 0 ) {
            return;
        }

        $nonce = isset( $_POST['birthday_bash_checkout_nonce_field'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday_bash_checkout_nonce_field'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'birthday_bash_checkout_action' ) ) {
            return;
        }

        $day   = isset( $_POST['birthday_bash_checkout_day'] ) ? absint( $_POST['birthday_bash_checkout_day'] ) : 0;
        $month = isset( $_POST['birthday_bash_checkout_month'] ) ? absint( $_POST['birthday_bash_checkout_month'] ) : 0;
        $fake_year = 2024;

        $is_valid_date = checkdate( $month, $day, $fake_year );
        $is_mandatory  = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );

        if ( WC()->session && method_exists( WC()->session, 'set' ) ) {
            if ( $is_valid_date ) {
                WC()->session->set( 'birthday_bash_checkout_day', $day );
                WC()->session->set( 'birthday_bash_checkout_month', $month );
            } elseif ( ! $is_mandatory ) {
                WC()->session->set( 'birthday_bash_checkout_day', '' );
                WC()->session->set( 'birthday_bash_checkout_month', '' );
            }
        }
    }

    public function save_guest_birthday_to_order( $order, $data ) {
        if ( $order->get_customer_id() > 0 ) {
            $day   = get_user_meta( $order->get_customer_id(), 'birthday_bash_birthday_day', true );
            $month = get_user_meta( $order->get_customer_id(), 'birthday_bash_birthday_month', true );
        } else {
            $nonce = isset( $_POST['birthday_bash_checkout_nonce_field'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday_bash_checkout_nonce_field'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'birthday_bash_checkout_action' ) ) {
                return;
            }
            $day   = isset( $_POST['birthday_bash_checkout_day'] ) ? absint( $_POST['birthday_bash_checkout_day'] ) : '';
            $month = isset( $_POST['birthday_bash_checkout_month'] ) ? absint( $_POST['birthday_bash_checkout_month'] ) : '';
        }

        if ( checkdate( (int) $month, (int) $day, 2024 ) ) {
            $order->update_meta_data( '_birthday_bash_birthday_day', $day );
            $order->update_meta_data( '_birthday_bash_birthday_month', $month );
        }
    }
}
