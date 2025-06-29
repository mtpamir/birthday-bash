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
        add_action( 'woocommerce_save_account_details', array( $this, 'save_birthday_field_my_account' ), 10, 1  );
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
                <input type="number" class="woocommerce-Input woocommerce-Input--text input-text" name="birthday_bash_birthday_day" id="birthday_bash_birthday_day" value="<?php echo esc_attr( $birthday_day ); ?>" min="1" max="31" <?php echo esc_attr( $required_attr ); ?> />
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
        </fieldset>
        <?php
    }

    /**
     * Save birthday field from My Account edit form.
     *
     * @param int $user_id The user ID.
     */
    public function save_birthday_field_my_account( $user_id ) {
        // Verify the WooCommerce "Edit Account" nonce
        if ( ! isset( $_POST['woocommerce-edit-account-nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['woocommerce-edit-account-nonce'] ), 'woocommerce-edit_account' ) ) {
            wc_add_notice( esc_html__( 'Security check failed. Please try again.', 'birthday-bash' ), 'error' );
            return;
        }
    
        $is_mandatory = get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $day          = isset( $_POST['birthday_bash_birthday_day'] ) ? absint( $_POST['birthday_bash_birthday_day'] ) : 0;
        $month        = isset( $_POST['birthday_bash_birthday_month'] ) ? absint( $_POST['birthday_bash_birthday_month'] ) : 0;
    
        // Use a generic leap year for checkdate validation
        $is_valid_date = checkdate( $month, $day, 2024 );
    
        // Case 1: Mandatory and invalid
        if ( $is_mandatory && ! $is_valid_date ) {
            wc_add_notice( esc_html__( 'Please enter a valid birthday (day and month).', 'birthday-bash' ), 'error' );
            delete_user_meta( $user_id, 'birthday_bash_birthday_day' );
            delete_user_meta( $user_id, 'birthday_bash_birthday_month' );
            return;
        }
    
        // Case 2: Valid date
        if ( $is_valid_date ) {
            update_user_meta( $user_id, 'birthday_bash_birthday_day', $day );
            update_user_meta( $user_id, 'birthday_bash_birthday_month', $month );
        } else {
            // Optional fields but invalid input (e.g. 31 Sep)
            delete_user_meta( $user_id, 'birthday_bash_birthday_day' );
            delete_user_meta( $user_id, 'birthday_bash_birthday_month' );
        }
    
        // Unsubscribe option handling
        if ( isset( $_POST['birthday_bash_unsubscribe_email'] ) && get_option( 'birthday_bash_unsubscribe_option', 1 ) ) {
            update_user_meta( $user_id, 'birthday_bash_unsubscribed', 1 );
        } else {
            update_user_meta( $user_id, 'birthday_bash_unsubscribed', 0 );
        }
    }
    
}