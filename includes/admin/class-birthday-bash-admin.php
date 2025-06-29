<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Admin
 *
 * Handles admin-specific functionalities for the free plugin.
 */
class BirthdayBash_Admin {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action( 'personal_options_update', array( $this, 'save_birthday_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_birthday_fields' ) );
        add_action( 'show_user_profile', array( $this, 'add_birthday_field_to_profile' ) );
        add_action( 'edit_user_profile', array( $this, 'add_birthday_field_to_profile' ) );
    }

    /**
     * Add birthday fields to user profile page in admin.
     *
     * @param WP_User $user The user object.
     */
    public function add_birthday_field_to_profile( $user ) {
        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $birthday_day   = get_user_meta( $user->ID, 'birthday_bash_birthday_day', true );
        $birthday_month = get_user_meta( $user->ID, 'birthday_bash_birthday_month', true );
        ?>
        <h3><?php esc_html_e( 'Birthday Information', 'birthday-bash' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="birthday_bash_birthday_day"><?php esc_html_e( 'Birthday Day', 'birthday-bash' ); ?></label></th>
                <td>
                    <input type="number" name="birthday_bash_birthday_day" id="birthday_bash_birthday_day" value="<?php echo esc_attr( $birthday_day ); ?>" class="regular-text" min="1" max="31" />
                    <p class="description"><?php esc_html_e( 'Enter the day of the month (e.g., 15)', 'birthday-bash' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="birthday_bash_birthday_month"><?php esc_html_e( 'Birthday Month', 'birthday-bash' ); ?></label></th>
                <td>
                    <select name="birthday_bash_birthday_month" id="birthday_bash_birthday_month">
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
                    <p class="description"><?php esc_html_e( 'Select the month of birth', 'birthday-bash' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
        // Add nonce field for security
        wp_nonce_field( 'birthday_bash_save_birthday_fields', 'birthday_bash_birthday_nonce' );
    }

    /**
     * Save birthday fields from user profile page.
     *
     * @param int $user_id The ID of the user.
     */
    public function save_birthday_fields( $user_id ) {
        // Verify nonce first for security
        if ( ! isset( $_POST['birthday_bash_birthday_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['birthday_bash_birthday_nonce'] ), 'birthday_bash_save_birthday_fields' ) ) {
            return; // Nonce verification failed, do not process
        }

        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        if ( isset( $_POST['birthday_bash_birthday_day'] ) ) {
            $day = absint( $_POST['birthday_bash_birthday_day'] );
            if ( $day >= 1 && $day <= 31 ) {
                update_user_meta( $user_id, 'birthday_bash_birthday_day', $day );
            } else {
                delete_user_meta( $user_id, 'birthday_bash_birthday_day' );
            }
        }

        if ( isset( $_POST['birthday_bash_birthday_month'] ) ) {
            $month = absint( $_POST['birthday_bash_birthday_month'] );
            if ( $month >= 1 && $month <= 12 ) {
                update_user_meta( $user_id, 'birthday_bash_birthday_month', $month );
            } else {
                delete_user_meta( $user_id, 'birthday_bash_birthday_month' );
            }
        }
    }
}