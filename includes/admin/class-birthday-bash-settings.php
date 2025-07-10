<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Settings
 *
 * Handles the plugin's settings page.
 */
class BirthdayBash_Settings {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        // Added a higher priority (99) to ensure scripts are enqueued correctly
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_uploader_scripts' ), 99 );
    }

    /**
     * Enqueue scripts and styles for the media uploader on the settings page.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_media_uploader_scripts( $hook ) {
        // Only load on the plugin's settings page.
        if ( 'toplevel_page_birthday-bash' !== $hook ) {
            return;
        }

        // Enqueue WordPress media scripts (this loads wp-media-models and others)
        wp_enqueue_media(); // Prepares the media frame components.

        // Register and enqueue custom media uploader script
        wp_register_script(
            'birthday-bash-media-script',
            BIRTHDAY_BASH_PLUGIN_URL . 'assets/js/admin/media-uploader.js',
            array( 'jquery', 'media-upload', 'thickbox' ),
            BIRTHDAY_BASH_VERSION,
            true
        );
        wp_enqueue_script( 'birthday-bash-media-script' );

        // Enqueue legacy media dependencies and styles
        wp_enqueue_script( 'media-upload' );
        wp_enqueue_script( 'thickbox' );
        wp_enqueue_style( 'thickbox' );

        // Enqueue custom styles for the media uploader
        wp_enqueue_style(
            'birthday-bash-media-uploader-style',
            BIRTHDAY_BASH_PLUGIN_URL . 'assets/css/admin/media-uploader.css',
            array(),
            BIRTHDAY_BASH_VERSION
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        // General Settings Section
        add_settings_section(
            'birthday_bash_general_settings_section',
            esc_html__( 'General Settings', 'birthday-bash' ),
            array( $this, 'general_settings_section_callback' ),
            'birthday-bash'
        );

        add_settings_field(
            'birthday_bash_coupon_type',
            esc_html__( 'Coupon Type', 'birthday-bash' ),
            array( $this, 'coupon_type_callback' ),
            'birthday-bash',
            'birthday_bash_general_settings_section'
        );

        add_settings_field(
            'birthday_bash_coupon_amount',
            esc_html__( 'Coupon Amount', 'birthday-bash' ),
            array( $this, 'coupon_amount_callback' ),
            'birthday-bash',
            'birthday_bash_general_settings_section'
        );

        add_settings_field(
            'birthday_bash_coupon_prefix',
            esc_html__( 'Coupon Code Prefix', 'birthday-bash' ),
            array( $this, 'coupon_prefix_callback' ),
            'birthday-bash',
            'birthday_bash_general_settings_section'
        );

        add_settings_field(
            'birthday_bash_birthday_field_mandatory',
            esc_html__( 'Make Birthday Field Mandatory', 'birthday-bash' ),
            array( $this, 'birthday_field_mandatory_callback' ),
            'birthday-bash',
            'birthday_bash_general_settings_section'
        );

        add_settings_field(
            'birthday_bash_unsubscribe_option',
            esc_html__( 'Enable Unsubscribe Option', 'birthday-bash' ),
            array( $this, 'unsubscribe_option_callback' ),
            'birthday-bash',
            'birthday_bash_general_settings_section'
        );

        add_settings_field(
            'birthday_bash_coupon_expiry_days',
            esc_html__( 'Coupon Expiry (Days)', 'birthday-bash' ),
            array( $this, 'coupon_expiry_days_callback' ),
            'birthday-bash',
            'birthday_bash_general_settings_section'
        );

        // Email Settings Section
        add_settings_section(
            'birthday_bash_email_settings_section',
            esc_html__( 'Email Settings', 'birthday-bash' ),
            array( $this, 'email_settings_section_callback' ),
            'birthday-bash'
        );

        add_settings_field(
            'birthday_bash_email_logo',
            esc_html__( 'Email Logo URL', 'birthday-bash' ),
            array( $this, 'email_logo_callback' ),
            'birthday-bash',
            'birthday_bash_email_settings_section'
        );

        add_settings_field(
            'birthday_bash_email_greeting',
            esc_html__( 'Email Greeting', 'birthday-bash' ),
            array( $this, 'email_greeting_callback' ),
            'birthday-bash',
            'birthday_bash_email_settings_section'
        );

        add_settings_field(
            'birthday_bash_email_message',
            esc_html__( 'Email Message', 'birthday-bash' ),
            array( $this, 'email_message_callback' ),
            'birthday-bash',
            'birthday_bash_email_settings_section'
        );

        // Register Settings Fields with sanitization callbacks
        register_setting( 'birthday-bash-settings-group', 'birthday_bash_coupon_type', array( 'sanitize_callback' => array( $this, 'sanitize_coupon_type' ) ) );
        register_setting( 'birthday-bash-settings-group', 'birthday_bash_coupon_amount', array( 'sanitize_callback' => array( $this, 'sanitize_coupon_amount' ) ) );
        register_setting( 'birthday-bash-settings-group', 'birthday_bash_coupon_prefix', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'birthday-bash-settings-group', 'birthday_bash_birthday_field_mandatory', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'birthday-bash-settings-group', 'birthday_bash_unsubscribe_option', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'birthday-bash-settings-group', 'birthday_bash_coupon_expiry_days', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'birthday-bash-settings-group', 'birthday_bash_email_logo', array( 'sanitize_callback' => 'esc_url_raw' ) );
        register_setting( 'birthday-bash-settings-group', 'birthday_bash_email_greeting', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
        register_setting( 'birthday-bash-settings-group', 'birthday_bash_email_message', array( 'sanitize_callback' => 'wp_kses_post' ) );
    }

    /**
     * General Settings section callback.
     */
    public function general_settings_section_callback() {
        echo '<p>' . esc_html__( 'Configure the general settings for your birthday coupons.', 'birthday-bash' ) . '</p>';
    }

    /**
     * Coupon Type field callback.
     */
    public function coupon_type_callback() {
        $option = get_option( 'birthday_bash_coupon_type', 'fixed_cart' );
        ?>
        <select name="birthday_bash_coupon_type" id="birthday_bash_coupon_type">
            <option value="fixed_cart" <?php selected( $option, 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed Cart Discount', 'birthday-bash' ); ?></option>
            <option value="percent" <?php selected( $option, 'percent' ); ?>><?php esc_html_e( 'Percentage Discount', 'birthday-bash' ); ?></option>
        </select>
        <?php
    }

    /**
     * Sanitize coupon type.
     *
     * @param string $input
     * @return string
     */
    public function sanitize_coupon_type( $input ) {
        return in_array( $input, array( 'fixed_cart', 'percent' ), true ) ? $input : 'fixed_cart';
    }

    /**
     * Sanitize and validate coupon amount.
     * Must be a number greater than 0.
     *
     * @param mixed $input The raw input value.
     * @return float The sanitized and validated amount, or the old value if invalid.
     */
    public function sanitize_coupon_amount( $input ) {
        $sanitized_amount = floatval( $input );

        if ( $sanitized_amount > 0 ) {
            return $sanitized_amount;
        }

        // If validation fails, set a settings error and return the previous valid option
        add_settings_error(
            'birthday_bash_coupon_amount',
            'invalid_amount',
            esc_html__( 'Coupon Amount must be a number greater than 0.', 'birthday-bash' ),
            'error'
        );

        return floatval( get_option( 'birthday_bash_coupon_amount', 10 ) );
    }

    /**
     * Coupon Amount field callback.
     */
    public function coupon_amount_callback() {
        $option = get_option( 'birthday_bash_coupon_amount', 10 );
        ?>
        <input type="number" name="birthday_bash_coupon_amount" id="birthday_bash_coupon_amount" value="<?php echo esc_attr( $option ); ?>" min="0.01" step="0.01" />
        <?php
    }

    /**
     * Coupon Prefix field callback.
     */
    public function coupon_prefix_callback() {
        $option = get_option( 'birthday_bash_coupon_prefix', 'BIRTHDAY-' );
        ?>
        <input type="text" name="birthday_bash_coupon_prefix" id="birthday_bash_coupon_prefix" value="<?php echo esc_attr( $option ); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e( 'Prefix for generated coupon codes (e.g., BIRTHDAY-)', 'birthday-bash' ); ?></p>
        <?php
    }

    /**
     * Birthday Field Mandatory field callback.
     */
    public function birthday_field_mandatory_callback() {
        $option = get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        ?>
        <input type="checkbox" name="birthday_bash_birthday_field_mandatory" id="birthday_bash_birthday_field_mandatory" value="1" <?php checked( $option, 1 ); ?> />
        <label for="birthday_bash_birthday_field_mandatory"><?php esc_html_e( 'Make the birthday field mandatory during checkout and registration.', 'birthday-bash' ); ?></label>
        <?php
    }

    /**
     * Unsubscribe Option field callback.
     */
    public function unsubscribe_option_callback() {
        $option = get_option( 'birthday_bash_unsubscribe_option', 1 );
        ?>
        <input type="checkbox" name="birthday_bash_unsubscribe_option" id="birthday_bash_unsubscribe_option" value="1" <?php checked( $option, 1 ); ?> />
        <label for="birthday_bash_unsubscribe_option"><?php esc_html_e( 'Allow users to unsubscribe from birthday coupon emails.', 'birthday-bash' ); ?></label>
        <?php
    }

    /**
     * Coupon Expiry Days field callback.
     */
    public function coupon_expiry_days_callback() {
        $option = get_option( 'birthday_bash_coupon_expiry_days', 14 );
        ?>
        <input type="number" name="birthday_bash_coupon_expiry_days" id="birthday_bash_coupon_expiry_days" value="<?php echo esc_attr( $option ); ?>" min="1" />
        <p class="description"><?php esc_html_e( 'Number of days until the birthday coupon expires after being issued.', 'birthday-bash' ); ?></p>
        <?php
    }

    /**
     * Email Settings section callback.
     */
    public function email_settings_section_callback() {
        echo '<p>' . esc_html__( 'Customize the birthday coupon email.', 'birthday-bash' ) . '</p>';
    }

    /**
     * Email Logo URL field callback.
     * Uses media library selector and outputs image with wp_get_attachment_image()
     */
    public function email_logo_callback() {
        $image_url = get_option( 'birthday_bash_email_logo', '' );
        $attachment_id = $this->get_attachment_id_by_url( $image_url );
        ?>
        <div class="birthday-bash-image-upload-wrap">
            <input type="hidden" name="birthday_bash_email_logo" id="birthday_bash_email_logo" value="<?php echo esc_url( $image_url ); ?>" class="regular-text" />
            <?php
            if ( $attachment_id ) {
                echo wp_get_attachment_image( $attachment_id, 'medium', false, array(
                    'id'    => 'birthday_bash_email_logo_preview',
                    'style' => 'max-width:200px; height:auto; margin-bottom:10px; display:block;',
                ) );
            } else {
                echo ''; // No image shown if no valid attachment ID
            }
            ?>
            <button type="button" class="button button-secondary birthday-bash-select-image-button">
                <?php echo empty( $image_url ) ? esc_html__( 'Select Image', 'birthday-bash' ) : esc_html__( 'Change Image', 'birthday-bash' ); ?>
            </button>
            <button type="button" class="button button-secondary birthday-bash-remove-image-button" style="display:<?php echo empty( $image_url ) ? 'none' : 'inline-block'; ?>;">
                <?php esc_html_e( 'Remove Image', 'birthday-bash' ); ?>
            </button>
            <p class="description"><?php esc_html_e( 'Select an image from the media library to use as your email logo.', 'birthday-bash' ); ?></p>
        </div>
        <?php
    }

    /**
     * Email Greeting field callback.
     */
    public function email_greeting_callback() {
        $option = get_option( 'birthday_bash_email_greeting', esc_html__( 'Happy Birthday, {customer_name}!', 'birthday-bash' ) );
        ?>
        <input type="text" name="birthday_bash_email_greeting" id="birthday_bash_email_greeting" value="<?php echo esc_attr( $option ); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e( 'Enter the greeting for the birthday email. Use {customer_name} for the customer\'s name.', 'birthday-bash' ); ?></p>
        <?php
    }

    /**
     * Email Message field callback.
     */
    public function email_message_callback() {
        $option = get_option( 'birthday_bash_email_message', esc_html__( 'As a special birthday treat, please enjoy this {coupon_type_text} off your next purchase. Use coupon code: ', 'birthday-bash' ) );
        wp_editor(
            $option,
            'birthday_bash_email_message',
            array(
                'textarea_name' => 'birthday_bash_email_message',
                'textarea_rows' => 10,
                'media_buttons' => false,
                'tinymce'       => true,
                'quicktags'     => true,
            )
        );
        ?>
        <p class="description">
            <?php esc_html_e( 'Customize the birthday email message. Available merge tags:', 'birthday-bash' ); ?><br/>
            <code>{customer_name}</code>, <code>{coupon_code}</code>, <code>{coupon_amount}</code>, <code>{coupon_type_text}</code>, <code>{coupon_expiry_date}</code>
        </p>
        <?php
    }

    /**
     * Get attachment ID by URL.
     *
     * Uses caching to reduce database calls.
     *
     * @param string $attachment_url URL of the attachment.
     * @return int|false Attachment ID or false if not found.
     */
    private function get_attachment_id_by_url( $attachment_url ) {
        global $wpdb;

        if ( empty( $attachment_url ) ) {
            return false;
        }

        // Try to get from cache
        $cache_key = 'birthday_bash_attachment_id_' . md5( $attachment_url );
        $cached = wp_cache_get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $upload_dir_paths = wp_upload_dir();

        // Remove upload base url from the attachment url to get relative path
        if ( false !== strpos( $attachment_url, $upload_dir_paths['baseurl'] ) ) {
            $attachment_url = str_replace( $upload_dir_paths['baseurl'] . '/', '', $attachment_url );
        }

        $attachment_id = attachment_url_to_postid( $attachment_url );

        // Cache the result
        wp_cache_set( $cache_key, $attachment_id );

        return $attachment_id;
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Birthday Bash Settings', 'birthday-bash' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'birthday-bash-settings-group' );
                do_settings_sections( 'birthday-bash' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
