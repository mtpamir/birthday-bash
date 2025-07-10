<?php
/**
 * Plugin Name: Birthday Bash
 * Description: Make your WooCommerce customers happy by automatically sending them personalized birthday coupons. It’s a simple way to build loyalty and encourage them to shop with you again.
 * Version:     1.0.0
 * Author:      MT Pamir
 * Author URI:  https://profiles.wordpress.org/mtpamir
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: birthday-bash
 * Domain Path: /languages
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Declare compatibility with High-Performance Order Storage (HPOS).
 *
 * This ensures the plugin works correctly when HPOS is enabled in WooCommerce.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Ensure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', 'birthday_bash_woocommerce_fallback_notice' );
    return;
}

/**
 * Fallback notice if WooCommerce is not active.
 */
function birthday_bash_woocommerce_fallback_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e( 'Birthday Bash requires WooCommerce to be installed and active. Please install and activate WooCommerce to use Birthday Bash.', 'birthday-bash' ); ?></p>
    </div>
    <?php
}

// Load the plugin after WooCommerce is fully loaded
add_action( 'woocommerce_loaded', 'birthday_bash_init' );

/**
 * Initialize Birthday Bash plugin.
 */
function birthday_bash_init() {
    // Check if Birthday Bash Pro is active
    if ( class_exists( 'BirthdayBash_Pro' ) ) {
        // If Pro is active, let Pro handle the initialization.
        // Pro version will extend and override free features as needed.
        return;
    }

    require_once plugin_dir_path( __FILE__ ) . 'includes/class-birthday-bash.php';
    BirthdayBash::get_instance();
}

/**
 * Add settings link to plugin actions row.
 *
 * @param array $links The array of plugin action links.
 * @return array The filtered array of plugin action links.
 */
function birthday_bash_add_settings_link( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=birthday-bash' ) ) . '">' . esc_html__( 'Settings', 'birthday-bash' ) . '</a>';
    array_unshift( $links, $settings_link ); // Add the settings link to the beginning of the array.
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'birthday_bash_add_settings_link' );


/**
 * Activation hook.
 */
register_activation_hook( __FILE__, 'birthday_bash_activate' );
function birthday_bash_activate() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/common/class-birthday-bash-db.php';
    BirthdayBash_DB::create_tables();
}

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, 'birthday_bash_deactivate' );
function birthday_bash_deactivate() {
    // Clean up any scheduled cron jobs
    wp_clear_scheduled_hook( 'birthday_bash_daily_cron' );
}

/**
 * Uninstallation hook.
 *
 * Note: uninstall.php handles database table deletion, etc.
 */
// register_uninstall_hook( __FILE__, 'birthday_bash_uninstall' ); // Handled by uninstall.php