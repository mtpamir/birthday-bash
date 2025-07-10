<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash
 *
 * The main class for the free Birthday Bash plugin.
 * Implements the Singleton pattern.
 */
class BirthdayBash {

    /**
     * The single instance of the class.
     *
     * @var BirthdayBash
     */
    protected static $instance = null;

    /**
     * Get the single instance of the class.
     *
     * @return BirthdayBash
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    protected function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define plugin constants.
     */
    private function define_constants() {
        define( 'BIRTHDAY_BASH_VERSION', '1.0.0' );
        define( 'BIRTHDAY_BASH_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) );
        // FIX: Ensure this line has the correct closing parenthesis
        define( 'BIRTHDAY_BASH_PLUGIN_URL', plugin_dir_url( dirname( __FILE__ ) ) );
        define( 'BIRTHDAY_BASH_BASENAME', plugin_basename( dirname( dirname( __FILE__ ) ) . '/birthday-bash.php' ) );
    }

    /**
     * Include necessary files.
     */
    private function includes() {
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/admin/class-birthday-bash-admin.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/admin/class-birthday-bash-settings.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/admin/class-birthday-bash-coupon-log.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/admin/class-birthday-bash-deactivation-feedback.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/frontend/class-birthday-bash-frontend.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/frontend/class-birthday-bash-my-account.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/frontend/class-birthday-bash-checkout.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/frontend/class-birthday-bash-gutenberg-blocks-free.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/frontend/class-birthday-bash-registration.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/common/class-birthday-bash-cron.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/common/class-birthday-bash-db.php';
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/class-birthday-bash-helper.php';
        // DO NOT include class-birthday-bash-email.php here.
        // It must be loaded when WC_Email is guaranteed to be available (via woocommerce_email_classes hook),
        // or explicitly within the method of BirthdayBash_Cron that uses it.
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Re-add this hook. It's safe now because BirthdayBash is initialized via 'woocommerce_loaded'
        // which means 'plugins_loaded' has already fired and the class is definitely available.
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 10 );

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

        add_filter( 'woocommerce_email_classes', array( $this, 'add_email_class_to_woocommerce' ) );

        // Initialize modules
        BirthdayBash_Admin::get_instance();
        BirthdayBash_Settings::get_instance();
        BirthdayBash_Coupon_Log::get_instance();
        BirthdayBash_Deactivation_Feedback::get_instance();
        BirthdayBash_Frontend::get_instance();
        BirthdayBash_My_Account::get_instance();
        BirthdayBash_Checkout::get_instance();
        BirthdayBash_Gutenberg_Blocks_Free::get_instance();
        BirthdayBash_Registration::get_instance();
        BirthdayBash_Cron::get_instance();
        BirthdayBash_DB::get_instance();
    }

    /**
     * Runs once all plugins are loaded.
     * This is where text domain loading traditionally happens.
     */
    public function on_plugins_loaded() {
        load_plugin_textdomain( 'birthday-bash', false, dirname( BIRTHDAY_BASH_BASENAME ) . '/languages' );
    }

    /**
     * Add admin menu item.
     */
    public function add_admin_menu() {
        add_menu_page(
            esc_html__( 'Birthday Bash', 'birthday-bash' ),
            esc_html__( 'Birthday Bash', 'birthday-bash' ),
            'manage_options',
            'birthday-bash',
            array( BirthdayBash_Settings::get_instance(), 'render_settings_page' ),
            'dashicons-buddicons-community',
            '55.6'
        );

        add_submenu_page(
            'birthday-bash',
            esc_html__( 'Coupon Logs', 'birthday-bash' ),
            esc_html__( 'Coupon Logs', 'birthday-bash' ),
            'manage_options',
            'birthday-bash-coupon-logs',
            array( BirthdayBash_Coupon_Log::get_instance(), 'render_log_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts() {
        wp_enqueue_style( 'birthday-bash-admin', BIRTHDAY_BASH_PLUGIN_URL . 'assets/css/birthday-bash.css', array(), BIRTHDAY_BASH_VERSION );
        wp_enqueue_script( 'birthday-bash-admin', BIRTHDAY_BASH_PLUGIN_URL . 'assets/js/birthday-bash.js', array( 'jquery' ), BIRTHDAY_BASH_VERSION, true );
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style( 'birthday-bash-frontend', BIRTHDAY_BASH_PLUGIN_URL . 'assets/css/birthday-bash.css', array(), BIRTHDAY_BASH_VERSION );
    }

    /**
     * Adds the Birthday Bash email class to WooCommerce.
     *
     * @param array $emails Array of WooCommerce email classes.
     * @return array Modified array of WooCommerce email classes.
     */
    public function add_email_class_to_woocommerce( $emails ) {
        // This is the correct place to require it for WooCommerce's email system
        // because WC_Email (parent) is guaranteed to be available by this point.
        require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/common/class-birthday-bash-email.php';
        $emails['BirthdayBash_Birthday_Coupon'] = BirthdayBash_Email::get_instance();
        return $emails;
    }
}