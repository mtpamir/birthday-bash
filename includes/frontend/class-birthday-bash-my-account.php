<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_My_Account
 *
 * Handles My Account page integration for the free plugin.
 */
class BirthdayBash_My_Account {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action( 'woocommerce_edit_account_form', array( $this, 'add_birthday_field_to_my_account' ) );
        // We will hook into woocommerce_save_account_details after WooCommerce's own nonce check
        // However, we'll add our own nonce for extra security specifically for our fields.
        add_action( 'woocommerce_save_account_details', array( $this, 'save_birthday_field_my_account' ), 10, 1 );
    }

    /**
     * Add birthday fields to My Account edit form.
     */
    public function add_birthday_field_to_my_account() {
        $user_id        = get_current_user_id();
        $birthday_day   = get_user_meta( $user_id, 'birthday_bash_birthday_day', true );
        $birthday_month = get_user_meta( $user_id, 'birthday_bash_birthday_month', true );

        $is_mandatory = get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $required_attr = $is_mandatory ? 'required' : '';
        ?>
        <fieldset>
            <legend><?php esc_html_e( 'Birthday Information', 'birthday-bash' ); ?></legend>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="birthday_bash_birthday_day"><?php esc_html_e( 'Birthday Day', 'birthday-bash' ); ?> <?php echo $is_mandatory ? '<span class="required">*</span>' : ''; ?></label>
                <input type="number" class="woocommerce-Input woocommerce-Input--text input-text" name="birthday_bash_birthday_day" id="birthday_bash_birthday_day" value="<?php echo esc_attr( $birthday_day ); ?>" min="1" max="31" <?php echo esc_attr( $required_attr ); ?> placeholder="<?php esc_attr_e( 'e.g., 15', 'birthday-bash' ); ?>" />
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="birthday_bash_birthday_month"><?php esc_html_e( 'Birthday Month', 'birthday-bash' ); ?> <?php echo $is_mandatory ? '<span class="required">*</span>' : ''; ?></label>
                <select name="birthday_bash_birthday_month" id="birthday_bash_birthday_month" class="woocommerce-Input woocommerce-Input--select" <?php echo esc_attr( $required_attr ); ?>>
                    <option value=""><?php esc_html_e( 'Select Month', 'birthday-bash' ); ?></option>
                    <?php
                    for ( $m = 1; $m <= 12; $m++ ) {
                        printf(
                            '<option value="%1$s" %2$s>%3$s</option>',
                            esc_attr( $m ),
                            selected( $birthday_month, $m, false ),
                            esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 10 ) ) )
                        );
                    }
                    ?>
                </select>
            </p>
            <?php
            $unsubscribe_option = get_option( 'birthday_bash_unsubscribe_option', 1 );
            if ( $unsubscribe_option ) {
                $unsubscribed = get_user_meta( $user_id, 'birthday_bash_unsubscribed', true );
                ?>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <input type="checkbox" name="birthday_bash_unsubscribe_email" id="birthday_bash_unsubscribe_email" value="1" <?php checked( $unsubscribed, 1 ); ?> />
                    <label for="birthday_bash_unsubscribe_email"><?php esc_html_e( 'Unsubscribe from birthday coupon emails', 'birthday-bash' ); ?></label>
                </p>
                <?php
            }
            ?>
            <?php wp_nonce_field( 'birthday_bash_save_birthday_details', 'birthday_bash_birthday_nonce' ); ?>
        </fieldset>
        <?php
    }

    /**
     * Save birthday field from My Account edit form.
     *
     * @param int $user_id The user ID.
     */
    public function save_birthday_field_my_account( $user_id ) {
        // Check and sanitize nonce
        $nonce = isset( $_POST['birthday_bash_birthday_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday_bash_birthday_nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'birthday_bash_save_birthday_details' ) ) {
            wc_add_notice( esc_html__( 'Security check failed. Please refresh the page and try again.', 'birthday-bash' ), 'error' );
            return;
        }

        $is_mandatory = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );

        // Sanitize and absint day and month inputs
        $day   = isset( $_POST['birthday_bash_birthday_day'] ) ? absint( wp_unslash( $_POST['birthday_bash_birthday_day'] ) ) : 0;
        $month = isset( $_POST['birthday_bash_birthday_month'] ) ? absint( wp_unslash( $_POST['birthday_bash_birthday_month'] ) ) : 0;

        // Basic validation for day and month values
        $is_valid_input_day   = ( $day >= 1 && $day <= 31 );
        $is_valid_input_month = ( $month >= 1 && $month <= 12 );

        // Use a dummy year for checkdate
        $is_valid_date_combination = checkdate( $month, $day, 2024 );

        if ( $is_mandatory ) {
            if ( ! $is_valid_input_day || ! $is_valid_input_month || ! $is_valid_date_combination ) {
                wc_add_notice( esc_html__( 'Please enter a valid birthday (day and month). For example, February 29 is not valid in non-leap years.', 'birthday-bash' ), 'error' );
                return;
            }
        }

        if ( $is_valid_date_combination ) {
            update_user_meta( $user_id, 'birthday_bash_birthday_day', $day );
            update_user_meta( $user_id, 'birthday_bash_birthday_month', $month );
        } elseif ( ! $is_mandatory && ( $day === 0 && $month === 0 ) ) {
            delete_user_meta( $user_id, 'birthday_bash_birthday_day' );
            delete_user_meta( $user_id, 'birthday_bash_birthday_month' );
        } elseif ( ! $is_mandatory && ( ! $is_valid_input_day || ! $is_valid_input_month ) ) {
            wc_add_notice( esc_html__( 'Invalid birthday entered. Please correct the day and month.', 'birthday-bash' ), 'error' );
            delete_user_meta( $user_id, 'birthday_bash_birthday_day' );
            delete_user_meta( $user_id, 'birthday_bash_birthday_month' );
        }

        if ( get_option( 'birthday_bash_unsubscribe_option', 1 ) ) {
            $unsubscribe = isset( $_POST['birthday_bash_unsubscribe_email'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday_bash_unsubscribe_email'] ) ) : '';
            if ( $unsubscribe === '1' ) {
                update_user_meta( $user_id, 'birthday_bash_unsubscribed', 1 );
            } else {
                update_user_meta( $user_id, 'birthday_bash_unsubscribed', 0 );
            }
        }
    }
}
