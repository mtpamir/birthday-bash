<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Deactivation_Feedback
 *
 * Handles the deactivation feedback modal for the Birthday Bash plugin.
 */
class BirthdayBash_Deactivation_Feedback {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_footer', array( $this, 'render_deactivation_modal' ) );
        add_action( 'wp_ajax_birthday_bash_deactivation_feedback', array( $this, 'handle_feedback_submission' ) );
    }

    /**
     * Enqueue scripts and styles for the deactivation modal.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        // Only load on the plugins page
        if ( 'plugins.php' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'birthday-bash-deactivation-feedback',
            BIRTHDAY_BASH_PLUGIN_URL . 'assets/css/deactivation-feedback.css',
            array(),
            BIRTHDAY_BASH_VERSION
        );

        wp_enqueue_script(
            'birthday-bash-deactivation-feedback',
            BIRTHDAY_BASH_PLUGIN_URL . 'assets/js/deactivation-feedback.js',
            array( 'jquery' ),
            BIRTHDAY_BASH_VERSION,
            true
        );

        wp_localize_script(
            'birthday-bash-deactivation-feedback',
            'birthday_bash_deactivation_vars',
            array(
                'ajax_url'        => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'birthday_bash_deactivation_feedback_nonce' ),
                'plugin_basename' => BIRTHDAY_BASH_BASENAME,
            )
        );
    }

    /**
     * Render the deactivation feedback modal HTML.
     */
    public function render_deactivation_modal() {
        global $pagenow;

        if ( 'plugins.php' !== $pagenow ) {
            return;
        }

        $reasons = array(
            ''                    => esc_html__( 'Select a reason...', 'birthday-bash' ), // Default/placeholder for dropdown
            'no_longer_needed'    => esc_html__( 'No longer needed', 'birthday-bash' ),
            'found_better_plugin' => esc_html__( 'Found a better plugin', 'birthday-bash' ),
            'did_not_work'        => esc_html__( 'Plugin didn’t work as expected', 'birthday-bash' ),
            'caused_errors'       => esc_html__( 'Caused errors or conflicts', 'birthday-bash' ),
            'slowed_site'         => esc_html__( 'Slowed down my site', 'birthday-bash' ),
            'missing_features'    => esc_html__( 'Missing features I needed', 'birthday-bash' ),
            'temporary'           => esc_html__( 'Temporary deactivation', 'birthday-bash' ),
            'just_testing'        => esc_html__( 'Just testing', 'birthday-bash' ),
            'other'               => esc_html__( 'Other (please specify)', 'birthday-bash' ),
        );
        ?>
        <div id="birthday-bash-deactivation-modal" style="display: none;">
            <div class="birthday-bash-modal-content">
                <form id="birthday-bash-deactivation-form" action="" method="post">
                    <h3><?php esc_html_e( 'If you have a moment, please tell us why you are deactivating Birthday Bash:', 'birthday-bash' ); ?></h3>

                    <div class="birthday-bash-reasons">
                        <label for="deactivation_reason">
                            <h4><?php esc_html_e( 'Reason for deactivation:', 'birthday-bash' ); ?></h4>
                        </label>
                        <select name="reason" id="deactivation_reason" required>
                            <?php foreach ( $reasons as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="reason_other_text" placeholder="<?php esc_attr_e( 'Please specify...', 'birthday-bash' ); ?>" style="display:none;"></textarea>
                    </div>

                    <div class="birthday-bash-feedback-field">
                        <label for="feedback_message">
                            <h4><?php esc_html_e( 'How could we improve the plugin?', 'birthday-bash' ); ?></h4>
                        </label>
                        <textarea id="feedback_message" name="feedback_message" rows="4" placeholder="<?php esc_attr_e( 'Share your thoughts here...', 'birthday-bash' ); ?>"></textarea>
                    </div>

                    <div class="birthday-bash-email-field">
                        <label for="user_email">
                            <h4><?php esc_html_e( 'Would you like us to contact you if we fix the issue or release a major update?', 'birthday-bash' ); ?></h4>
                        </label>
                        <input type="email" id="user_email" name="user_email" placeholder="<?php esc_attr_e( 'Your Email (Optional)', 'birthday-bash' ); ?>" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">

                        <label for="additional_contact_email">
                            <h4><?php esc_html_e( 'Enter an alternative email for follow-up (optional):', 'birthday-bash' ); ?></h4>
                        </label>
                        <input type="email" id="additional_contact_email" name="additional_contact_email" placeholder="<?php esc_attr_e( 'e.g., support@yourcompany.com', 'birthday-bash' ); ?>">
                    </div>

                    <div class="birthday-bash-consent-field">
                        <label>
                            <input type="checkbox" name="consent_data_collection" value="1" id="consent_data_collection" required>
                            <?php
                            printf(
                                /* translators: %s privacy policy link */
                                esc_html__( 'I consent to sharing this feedback to help improve the plugin. Anonymous technical data (like WordPress version, theme, and plugin list) is collected. No personal data is stored unless you provide it in an email field. Read our %s.', 'birthday-bash' ),
                                '<a href="' . esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : '#' ) . '" target="_blank">' . esc_html__( 'Privacy Policy', 'birthday-bash' ) . '</a>'
                            );
                            ?>
                            <span class="required">*</span>
                        </label>

                        <div class="birthday-bash-admin-contact-consent" style="display:none; margin-top: 15px; padding-left: 20px; border-left: 3px solid #007cba;">
                            <label>
                                <input type="checkbox" name="consent_contact_all_admins" value="1">
                                <?php esc_html_e( 'I consent to allow you to contact ALL administrators of this site regarding issues or updates related to this feedback.', 'birthday-bash' ); ?>
                            </label>
                        </div>
                    </div>

                    <div class="birthday-bash-modal-buttons">
                        <button type="submit" class="button button-primary birthday-bash-submit-feedback">
                            <?php esc_html_e( 'Submit & Deactivate', 'birthday-bash' ); ?>
                        </button>
                        <button type="button" class="button birthday-bash-skip-feedback">
                            <?php esc_html_e( 'Skip & Deactivate', 'birthday-bash' ); ?>
                        </button>
                    </div>
                    <a href="#" class="birthday-bash-cancel-deactivation"><?php esc_html_e( 'Cancel Deactivation', 'birthday-bash' ); ?></a>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle the AJAX feedback submission.
     */
    public function handle_feedback_submission() {
        // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- String does not contain placeholders.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'birthday_bash_deactivation_feedback_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Invalid nonce.', 'birthday-bash' ) );
        }

        $reason                     = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
        $reason_other_text          = isset( $_POST['reason_other_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason_other_text'] ) ) : '';
        $feedback_message           = isset( $_POST['feedback_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback_message'] ) ) : '';
        $user_email                 = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
        $additional_contact_email   = isset( $_POST['additional_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['additional_contact_email'] ) ) : '';
        $consent_data_collection    = isset( $_POST['consent_data_collection'] ) ? true : false;
        $consent_contact_all_admins = isset( $_POST['consent_contact_all_admins'] ) ? true : false;
        $plugin_version             = BIRTHDAY_BASH_VERSION;

        if ( ! $consent_data_collection ) {
            wp_send_json_error( esc_html__( 'Consent for data collection is required.', 'birthday-bash' ) );
        }

        // Gather plugin usage context
        global $wp_version;
        // Removed: $active_plugins_count = count( (array) get_option( 'active_plugins', array() ) );

        // Get Active Theme info
        $current_theme_object = wp_get_theme();
        $active_theme_info = array(
            'name'    => $current_theme_object->get( 'Name' ),
            'version' => $current_theme_object->get( 'Version' ),
        );

        // Get List of ALL Plugins (active and inactive)
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins_installed = get_plugins();
        $all_plugins_list = array();
        $active_plugins_wp_option = (array) get_option( 'active_plugins', array() ); // Get paths of active plugins

        foreach ( $all_plugins_installed as $plugin_path => $plugin_data ) {
            $status = in_array( $plugin_path, $active_plugins_wp_option ) ? 'active' : 'inactive';
            $all_plugins_list[] = array(
                'name'    => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'status'  => $status,
                'path'    => $plugin_path, // Keep path for precise identification
            );
        }

        // Get Admin User Emails ONLY if explicit consent is given
        $admin_user_emails = array();
        if ( $consent_contact_all_admins ) {
            $admin_users = get_users( array( 'role' => 'administrator', 'fields' => array( 'user_email' ) ) );
            foreach ( $admin_users as $admin_user ) {
                $admin_user_emails[] = $admin_user->user_email;
            }
        }

        $feedback_data = array(
            'plugin'                     => 'Birthday Bash',
            'version'                    => BIRTHDAY_BASH_VERSION,
            'reason'                     => $reason,
            'reason_other_text'          => ( 'other' === $reason ) ? $reason_other_text : '',
            'feedback_message'           => $feedback_message,
            'user_email'                 => is_email( $user_email ) ? $user_email : '',
            'additional_contact_email'   => is_email( $additional_contact_email ) ? $additional_contact_email : '',
            'consent_contact_all_admins' => $consent_contact_all_admins,
            'wp_version'                 => $wp_version,
            'php_version'                => phpversion(),
            // Removed: 'active_plugins_count' => $active_plugins_count,
            'active_theme_info'          => $active_theme_info,
            'all_plugins_list'           => $all_plugins_list, // Updated to all plugins
            'admin_user_emails'          => $admin_user_emails,
            'deactivation_time'          => current_time( 'mysql' ),
        );

        // --- SIMULATE SENDING DATA TO AN EXTERNAL SERVER ---
        // IMPORTANT: Replace 'https://your-feedback-server.com/api/feedback' with your actual endpoint.
        // This is a placeholder for demonstration purposes.
        $remote_url = 'https://your-feedback-server.com/api/feedback';

        $response = wp_remote_post( $remote_url, array(
            'method'      => 'POST',
            'timeout'     => 15,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array( 'Content-Type' => 'application/json; charset=' . get_option( 'blog_charset' ) ),
            'body'        => wp_json_encode( $feedback_data ),
        ) );

        if ( is_wp_error( $response ) ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log critical errors for debugging.
            error_log( 'Birthday Bash Deactivation Feedback Error: ' . $response->get_error_message() );
            wp_send_json_success( array( 'message' => esc_html__( 'Feedback submitted (with error log on server).', 'birthday-bash' ), 'status' => 'error_logged' ) );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            if ( $response_code >= 200 && $response_code < 300 ) {
                wp_send_json_success( array( 'message' => esc_html__( 'Feedback submitted successfully.', 'birthday-bash' ), 'status' => 'success' ) );
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log critical errors for debugging.
                error_log( 'Birthday Bash Deactivation Feedback Failed: HTTP ' . $response_code . ' - ' . wp_remote_retrieve_body( $response ) );
                wp_send_json_error( array( 'message' => esc_html__( 'Failed to submit feedback to server (non-2xx response).', 'birthday-bash' ), 'status' => 'failed' ) );
            }
        }
    }
}