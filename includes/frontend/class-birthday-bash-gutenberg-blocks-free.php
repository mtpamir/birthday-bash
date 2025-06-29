<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Gutenberg_Blocks_Free
 *
 * Registers and handles the Birthday Input Form Gutenberg block for the free plugin.
 */
class BirthdayBash_Gutenberg_Blocks_Free {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action( 'init', array( $this, 'register_birthday_input_block' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
        add_action( 'wp_ajax_birthday_bash_save_birthday_block_free', array( $this, 'ajax_save_birthday_block_free' ) );
        add_action( 'wp_ajax_nopriv_birthday_bash_save_birthday_block_free', array( $this, 'ajax_save_birthday_block_free' ) );
    }

    /**
     * Register the Gutenberg block.
     */
    public function register_birthday_input_block() {
        // Register the block script for the editor
        wp_register_script(
            'birthday-bash-free-block-editor',
            BIRTHDAY_BASH_PLUGIN_URL . 'assets/js/blocks/birthday-input-free.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
            BIRTHDAY_BASH_VERSION,
            true
        );

        // Register block editor style
        wp_register_style(
            'birthday-bash-free-block-editor-style',
            BIRTHDAY_BASH_PLUGIN_URL . 'assets/css/blocks/birthday-input-free-editor.css',
            array( 'wp-edit-blocks' ),
            BIRTHDAY_BASH_VERSION
        );

        // Register block frontend style
        wp_register_style(
            'birthday-bash-free-frontend-style',
            BIRTHDAY_BASH_PLUGIN_URL . 'assets/css/frontend/birthday-form.css',
            array(),
            BIRTHDAY_BASH_VERSION
        );

        // Register block frontend script for AJAX submission
        wp_register_script(
            'birthday-bash-free-frontend-script',
            BIRTHDAY_BASH_PLUGIN_URL . 'assets/js/frontend/birthday-form.js',
            array( 'jquery' ),
            BIRTHDAY_BASH_VERSION,
            true
        );

        // Localize script for AJAX calls
        wp_localize_script(
            'birthday-bash-free-frontend-script',
            'birthday_bash_free_block_vars',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'birthday_bash_save_birthday_block_nonce_free' ),
                'is_mandatory' => (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 ),
                'messages' => array(
                    'success' => esc_html__( 'Your birthday has been saved successfully!', 'birthday-bash' ),
                    'error'   => esc_html__( 'Please enter a valid birthday (day and month).', 'birthday-bash' ),
                    'not_logged_in' => esc_html__( 'You must be logged in to save your birthday.', 'birthday-bash' ),
                    'birthday_cleared' => esc_html__( 'Birthday information cleared.', 'birthday-bash' ), // Message for clearing
                ),
            )
        );

        register_block_type( 'birthday-bash/birthday-input-free', array(
            'editor_script' => 'birthday-bash-free-block-editor',
            'editor_style'  => 'birthday-bash-free-block-editor-style',
            'style'         => 'birthday-bash-free-frontend-style',
            'script'        => 'birthday-bash-free-frontend-script',
            'render_callback' => array( $this, 'render_birthday_input_form_block' ),
        ) );
    }

    /**
     * Enqueue block editor assets specific to this block.
     * Only enqueued if Gutenberg editor is active.
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script( 'birthday-bash-free-block-editor' );
        wp_enqueue_style( 'birthday-bash-free-block-editor-style' );
    }

    /**
     * Render the Birthday Input Form block for the frontend.
     *
     * @param array $attributes Block attributes.
     * @return string HTML output for the block.
     */
    public function render_birthday_input_form_block( $attributes ) {
        $user_id = get_current_user_id();
        $birthday_day = '';
        $birthday_month = '';

        if ( $user_id ) {
            $birthday_day   = get_user_meta( $user_id, 'birthday_bash_birthday_day', true );
            $birthday_month = get_user_meta( $user_id, 'birthday_bash_birthday_month', true );
        }

        $is_mandatory = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );
        $required_attr = $is_mandatory ? 'required' : ''; // This string is safe as an attribute

        ob_start();
        ?>
        <div class="birthday-bash-gutenberg-block-form">
            <h3><?php esc_html_e( 'Enter Your Birthday', 'birthday-bash' ); ?></h3>
            <?php if ( ! is_user_logged_in() ) : ?>
                <p class="birthday-bash-form-info"><?php esc_html_e( 'Please log in or register to save your birthday. If you are on the checkout page, your birthday will be saved automatically.', 'birthday-bash' ); ?></p>
            <?php endif; ?>
            <form class="birthday-bash-block-birthday-form">
                <p class="form-row form-row-first">
                    <label for="birthday_bash_block_day"><?php esc_html_e( 'Birthday Day', 'birthday-bash' ); ?> <?php echo $is_mandatory ? '<span class="required">*</span>' : ''; ?></label>
                    <input type="number" class="input-text" name="birthday_bash_block_day" id="birthday_bash_block_day" value="<?php echo esc_attr( $birthday_day ); ?>" min="1" max="31" <?php echo esc_attr( $required_attr ); ?> />
                </p>
                <p class="form-row form-row-last">
                    <label for="birthday_bash_block_month"><?php esc_html_e( 'Birthday Month', 'birthday-bash' ); ?> <?php echo $is_mandatory ? '<span class="required">*</span>' : ''; ?></label>
                    <select name="birthday_bash_block_month" id="birthday_bash_block_month" class="input-text" <?php echo esc_attr( $required_attr ); ?>>
                        <?php
                        // BirthdayBash_Helper::get_months_for_select returns HTML options,
                        // which are already internally escaped. wp_kses_post for output.
                        echo wp_kses_post( BirthdayBash_Helper::get_months_for_select( esc_html__( 'Select Month', 'birthday-bash' ), $birthday_month ) );
                        ?>
                    </select>
                </p>
                <?php wp_nonce_field( 'birthday_bash_save_birthday_block_nonce_free', 'birthday_bash_block_nonce_field' ); ?>
                <p class="form-row form-row-wide">
                    <button type="submit" class="button alt"><?php esc_html_e( 'Save Birthday', 'birthday-bash' ); ?></button>
                </p>
                <div class="birthday-bash-block-message" style="margin-top: 15px;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler to save birthday data from the block.
     */
    public function ajax_save_birthday_block_free() {
        check_ajax_referer( 'birthday_bash_save_birthday_block_nonce_free', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => birthday_bash_free_block_vars['messages']['not_logged_in'] ) );
        }

        $user_id = get_current_user_id();
        $day     = isset( $_POST['day'] ) ? absint( $_POST['day'] ) : 0;
        $month   = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : 0;
        $fake_year = 2024; // A leap year for checkdate validation

        $is_mandatory = (bool) get_option( 'birthday_bash_birthday_field_mandatory', 0 );

        if ( $is_mandatory && ( $day < 1 || $day > 31 || $month < 1 || $month > 12 || ! checkdate( $month, $day, $fake_year ) ) ) {
            wp_send_json_error( array( 'message' => birthday_bash_free_block_vars['messages']['error'] ) );
        } elseif ( $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && checkdate( $month, $day, $fake_year ) ) {
            update_user_meta( $user_id, 'birthday_bash_birthday_day', $day );
            update_user_meta( $user_id, 'birthday_bash_birthday_month', $month );
            wp_send_json_success( array( 'message' => birthday_bash_free_block_vars['messages']['success'] ) );
        } else {
            // If not mandatory and values are invalid, ensure metas are removed.
            delete_user_meta( $user_id, 'birthday_bash_birthday_day' );
            delete_user_meta( $user_id, 'birthday_bash_birthday_month' );
            // Use a specific message for clearing, as it's not an "error" but an action.
            wp_send_json_success( array( 'message' => birthday_bash_free_block_vars['messages']['birthday_cleared'] ) );
        }
    }
}