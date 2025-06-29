<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Registration
 *
 * Handles adding and saving birthday fields on the WooCommerce and default WordPress registration forms.
 */
class BirthdayBash_Registration {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        // Hooks for WooCommerce registration form
        add_action( 'woocommerce_register_form', array( $this, 'add_birthday_fields_to_registration_form' ) );
        add_filter( 'woocommerce_register_post', array( $this, 'validate_registration_birthday_fields_wc' ), 10, 3 );
        add_action( 'woocommerce_created_customer', array( $this, 'save_registration_birthday_fields' ), 10, 1 );

        // Hooks for default WordPress registration form (e.g., wp-login.php?action=register)
        add_action( 'register_form', array( $this, 'add_birthday_fields_to_registration_form' ) );
        add_filter( 'registration_errors', array( $this, 'validate_registration_birthday_fields_wp' ), 10, 3 );
        add_action( 'user_register', array( $this, 'save_registration_birthday_fields' ), 10, 1 );
    }

    /**
     * Add birthday fields to the registration form (both WooCommerce and default WP).
     */
    public function add_birthday_fields_to_registration_form() {
        $is_mandatory = get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $required_attr = $is_mandatory ? 'required' : '';

        // Check if we are on the default WordPress registration page
        $is_wp_register_page = ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] && isset( $_GET['action'] ) && 'register' === $_GET['action'] );

        // WooCommerce registration form uses its own styling classes.
        // Default WP registration form is simpler.
        $wrapper_class_day = $is_wp_register_page ? 'form-row' : 'form-row form-row-first';
        $wrapper_class_month = $is_wp_register_page ? 'form-row' : 'form-row form-row-last';
        $input_class = $is_wp_register_page ? '' : 'input-text'; // WP uses regular input, WC uses input-text

        ?>
        <p class="<?php echo esc_attr( $wrapper_class_day ); ?>">
            <label for="reg_birthday_bash_birthday_day"><?php esc_html_e( 'Birthday Day', 'birthday-bash' ); ?> <?php echo $is_mandatory ? '<span class="required">*</span>' : ''; ?></label>
            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Displaying pre-filled value from $_POST, not processing. Value is escaped.
            ?>
            <input type="number" class="<?php echo esc_attr( $input_class ); ?>" name="birthday_bash_birthday_day" id="reg_birthday_bash_birthday_day" value="<?php echo ( ! empty( $_POST['birthday_bash_birthday_day'] ) ) ? esc_attr( wp_unslash( $_POST['birthday_bash_birthday_day'] ) ) : ''; ?>" min="1" max="31" <?php echo esc_attr( $required_attr ); ?> />
        </p>
        <p class="<?php echo esc_attr( $wrapper_class_month ); ?>">
            <label for="reg_birthday_bash_birthday_month"><?php esc_html_e( 'Birthday Month', 'birthday-bash' ); ?> <?php echo $is_mandatory ? '<span class="required">*</span>' : ''; ?></label>
            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Displaying pre-filled value from $_POST, not processing. Value is escaped.
            ?>
            <select name="birthday_bash_birthday_month" id="reg_birthday_bash_birthday_month" class="<?php echo esc_attr( $input_class ); ?>" <?php echo esc_attr( $required_attr ); ?>>
                <?php
                $selected_month = ( ! empty( $_POST['birthday_bash_birthday_month'] ) ) ? absint( wp_unslash( $_POST['birthday_bash_birthday_month'] ) ) : '';
                echo wp_kses_post( BirthdayBash_Helper::get_months_for_select( esc_html__( 'Select Month', 'birthday-bash' ), $selected_month ) );
                ?>
            </select>
        </p>
        <?php if ( ! $is_wp_register_page ) : // Add clear div only for WC form for better layout ?>
        <div class="clear"></div>
        <?php endif;
    }

    /**
     * Validate birthday fields on WooCommerce registration.
     *
     * @param string $username User's chosen username.
     * @param string $email User's chosen email.
     * @param WP_Error $errors WP_Error object.
     * @return WP_Error
     */
    public function validate_registration_birthday_fields_wc( $username, $email, $errors ) {
        // Verify WooCommerce registration nonce
        if ( ! isset( $_POST['woocommerce-register-nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['woocommerce-register-nonce'] ), 'woocommerce-register-nonce' ) ) {
            $errors->add( 'nonce_error', esc_html__( 'Security check failed during registration. Please try again.', 'birthday-bash' ) );
            return $errors;
        }

        $is_mandatory = get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $raw_day      = isset( $_POST['birthday_bash_birthday_day'] ) ? wp_unslash( $_POST['birthday_bash_birthday_day'] ) : ''; // Retrieve raw input
        $day          = absint( $raw_day ); // Sanitize
        $raw_month    = isset( $_POST['birthday_bash_birthday_month'] ) ? wp_unslash( $_POST['birthday_bash_birthday_month'] ) : ''; // Retrieve raw input
        $month        = absint( $raw_month ); // Sanitize
        $fake_year    = 2024; // A leap year for checkdate validation

        if ( $is_mandatory && ( $day < 1 || $day > 31 || $month < 1 || $month > 12 || ! checkdate( $month, $day, $fake_year ) ) ) {
            $errors->add( 'birthday_bash_date_error', esc_html__( 'Please enter a valid birthday (day and month).', 'birthday-bash' ) );
        }
        return $errors;
    }

    /**
     * Validate birthday fields on default WordPress registration.
     *
     * @param WP_Error $errors WP_Error object.
     * @param string $sanitized_user_login User's chosen username.
     * @param string $user_email User's chosen email.
     * @return WP_Error
     */
    public function validate_registration_birthday_fields_wp( $errors, $sanitized_user_login, $user_email ) {
        // Verify default WordPress registration nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'register' ) ) {
            $errors->add( 'nonce_error', esc_html__( 'Security check failed during registration. Please try again.', 'birthday-bash' ) );
            return $errors;
        }

        $is_mandatory = get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $raw_day      = isset( $_POST['birthday_bash_birthday_day'] ) ? wp_unslash( $_POST['birthday_bash_birthday_day'] ) : ''; // Retrieve raw input
        $day          = absint( $raw_day ); // Sanitize
        $raw_month    = isset( $_POST['birthday_bash_birthday_month'] ) ? wp_unslash( $_POST['birthday_bash_birthday_month'] ) : ''; // Retrieve raw input
        $month        = absint( $raw_month ); // Sanitize
        $fake_year    = 2024; // A leap year for checkdate validation

        if ( $is_mandatory && ( $day < 1 || $day > 31 || $month < 1 || $month > 12 || ! checkdate( $month, $day, $fake_year ) ) ) {
            $errors->add( 'birthday_bash_date_error', esc_html__( 'Please enter a valid birthday (day and month).', 'birthday-bash' ) );
        }
        return $errors;
    }

    /**
     * Save birthday fields after customer creation (both WooCommerce and default WP).
     *
     * @param int $customer_id The ID of the newly created customer.
     */
    public function save_registration_birthday_fields( $customer_id ) {
        // Nonce check is performed in validation methods which run before save.
        // Data is assumed to be validated and sanitized by this point,
        // but we still sanitize here for robustness as $_POST is global.

        $raw_day   = isset( $_POST['birthday_bash_birthday_day'] ) ? wp_unslash( $_POST['birthday_bash_birthday_day'] ) : '';
        $day       = absint( $raw_day );
        $raw_month = isset( $_POST['birthday_bash_birthday_month'] ) ? wp_unslash( $_POST['birthday_bash_birthday_month'] ) : '';
        $month     = absint( $raw_month );
        $fake_year = 2024; // For consistency in validation logic.

        // Only save if valid date is provided.
        $should_save = ( $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && checkdate( $month, $day, $fake_year ) );

        if ( $should_save ) {
            update_user_meta( $customer_id, 'birthday_bash_birthday_day', $day );
            update_user_meta( $customer_id, 'birthday_bash_birthday_month', $month );
        } else {
            // If not valid, ensure metas are removed/not set.
            delete_user_meta( $customer_id, 'birthday_bash_birthday_day' );
            delete_user_meta( $customer_id, 'birthday_bash_birthday_month' );
        }
    }
}