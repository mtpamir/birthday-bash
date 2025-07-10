<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Registration
 *
 * Handles registration page integration for the free plugin.
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
        // Hook for adding fields to the default WordPress registration form
        add_action( 'register_form', array( $this, 'add_birthday_fields_to_registration_form' ) );
        // Hook for validating fields on registration
        add_filter( 'registration_errors', array( $this, 'validate_birthday_fields_registration' ), 10, 3 );
        // Hook for saving fields on user registration
        add_action( 'user_register', array( $this, 'save_birthday_fields_registration' ), 10, 1 );
    }

    /**
     * Add birthday fields to the WordPress registration form.
     */
    public function add_birthday_fields_to_registration_form() {
        // In a registration form, there's no pre-existing user data to load initially.
        // $birthday_day   = isset( $_POST['birthday_bash_registration_day'] ) ? absint( $_POST['birthday_bash_registration_day'] ) : '';
        // $birthday_month = isset( $_POST['birthday_bash_registration_month'] ) ? absint( $_POST['birthday_bash_registration_month'] ) : '';

        $birthday_day = '';
        $birthday_month = '';
        $nonce = isset($_POST['birthday_bash_registration_nonce']) ? sanitize_text_field(wp_unslash($_POST['birthday_bash_registration_nonce'])) : '';
        
        if ( $nonce && wp_verify_nonce( $nonce, 'birthday_bash_registration_action' ) ) {
            $birthday_day   = isset( $_POST['birthday_bash_registration_day'] ) ? absint( wp_unslash( $_POST['birthday_bash_registration_day'] ) ) : '';
            $birthday_month = isset( $_POST['birthday_bash_registration_month'] ) ? absint( wp_unslash( $_POST['birthday_bash_registration_month'] ) ) : '';
        }
        


        $is_mandatory = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $required_attr = $is_mandatory ? 'required' : '';

        // Get month options as an array from the helper
        $month_options = BirthdayBash_Helper::get_months_for_select( esc_html__( 'Select Month', 'birthday-bash' ) );

        ?>
        <p>
            <label for="birthday_bash_registration_day"><?php esc_html_e( 'Birthday Day', 'birthday-bash' ); ?> <?php echo $is_mandatory ? '<span class="required">*</span>' : ''; ?></label>
            <input type="number"
                   name="birthday_bash_registration_day"
                   id="birthday_bash_registration_day"
                   class="input"
                   value="<?php echo esc_attr( $birthday_day ); ?>"
                   min="1"
                   max="31"
                   <?php echo esc_attr( $required_attr ); ?> />
        </p>

        <p>
            <label for="birthday_bash_registration_month"><?php esc_html_e( 'Birthday Month', 'birthday-bash' ); ?> <?php echo $is_mandatory ? '<span class="required">*</span>' : ''; ?></label>
            <select name="birthday_bash_registration_month"
                    id="birthday_bash_registration_month"
                    class="input"
                    <?php echo esc_attr( $required_attr ); ?>>
                <?php
                foreach ( $month_options as $value => $label ) {
                    printf(
                        '<option value="%1$s" %2$s>%3$s</option>',
                        esc_attr( $value ),
                        selected( $birthday_month, $value, false ),
                        esc_html( $label )
                    );
                }
                ?>
            </select>
        </p>

        <?php
        // Add nonce field for security
        wp_nonce_field( 'birthday_bash_registration_action', 'birthday_bash_registration_nonce' );
    }

    /**
     * Validate birthday fields on registration.
     *
     * @param WP_Error $errors              A WP_Error object.
     * @param string   $sanitized_user_login The user's username.
     * @param string   $user_email          The user's email address.
     * @return WP_Error
     */
    public function validate_birthday_fields_registration( $errors, $sanitized_user_login, $user_email ) {
        // Verify nonce first - sanitize and unslash nonce from $_POST
        $nonce = isset( $_POST['birthday_bash_registration_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday_bash_registration_nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'birthday_bash_registration_action' ) ) {
            $errors->add( 'birthday_nonce_error', esc_html__( 'Security check failed. Please try again.', 'birthday-bash' ) );
            return $errors;
        }

        // Now safe to access and sanitize the other $_POST fields
        $is_mandatory = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $day          = isset( $_POST['birthday_bash_registration_day'] ) ? absint( wp_unslash( $_POST['birthday_bash_registration_day'] ) ) : 0;
        $month        = isset( $_POST['birthday_bash_registration_month'] ) ? absint( wp_unslash( $_POST['birthday_bash_registration_month'] ) ) : 0;
        $fake_year    = 2024; // A leap year for checkdate validation

        $day_has_value   = ( $day > 0 );
        $month_has_value = ( $month > 0 );

        if ( $is_mandatory ) {
            if ( ! $day_has_value || ! $month_has_value || ! checkdate( $month, $day, $fake_year ) ) {
                $errors->add( 'birthday_error', esc_html__( 'Please enter a valid birthday. Both day and month are required and must form a correct date.', 'birthday-bash' ) );
            }
        } else {
            if ( ( $day_has_value || $month_has_value ) && ! checkdate( $month, $day, $fake_year ) ) {
                $errors->add( 'birthday_error', esc_html__( 'The birthday date you provided is invalid. Please correct it or leave both fields empty.', 'birthday-bash' ) );
            }
        }

        return $errors;
    }

    /**
     * Save birthday fields on user registration.
     *
     * @param int $user_id The new user's ID.
     */
    public function save_birthday_fields_registration( $user_id ) {
        // Check nonce first
        $nonce = isset( $_POST['birthday_bash_registration_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday_bash_registration_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'birthday_bash_registration_action' ) ) {
            // Do not save if nonce invalid
            return;
        }

        $is_mandatory = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $day          = isset( $_POST['birthday_bash_registration_day'] ) ? absint( wp_unslash( $_POST['birthday_bash_registration_day'] ) ) : 0;
        $month        = isset( $_POST['birthday_bash_registration_month'] ) ? absint( wp_unslash( $_POST['birthday_bash_registration_month'] ) ) : 0;
        $fake_year    = 2024;

        $is_valid_date = checkdate( $month, $day, $fake_year );

        if ( $is_valid_date ) {
            update_user_meta( $user_id, 'birthday_bash_birthday_day', $day );
            update_user_meta( $user_id, 'birthday_bash_birthday_month', $month );
        } elseif ( ! $is_mandatory && ( $day === 0 && $month === 0 ) ) {
            // If not mandatory AND both fields are empty, ensure no meta is saved.
            delete_user_meta( $user_id, 'birthday_bash_birthday_day' );
            delete_user_meta( $user_id, 'birthday_bash_birthday_month' );
        }
        // If mandatory and invalid, validation (registration_errors) should have caught it.
    }
}
