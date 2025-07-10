<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Coupon_Log
 *
 * Displays a basic log of issued birthday coupons in the admin.
 */
class BirthdayBash_Coupon_Log {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        // No direct actions/filters for free log display, it's rendered via menu.
    }

    /**
     * Render the coupon log page.
     */
    public function render_log_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Birthday Coupon Logs', 'birthday-bash' ); ?></h1>
            <p><?php esc_html_e( 'This section displays a basic log of issued birthday coupons.', 'birthday-bash' ); ?></p>
            <?php
            // Ensure BirthdayBash_DB is loaded and initialized to access its methods.
            // It should already be included via BirthdayBash::includes() and initialized via get_instance()
            // but a defensive check is good for direct access or if order of execution is tricky.
            if ( ! class_exists( 'BirthdayBash_DB' ) ) {
                require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/common/class-birthday-bash-db.php';
            }
            BirthdayBash_DB::get_instance(); // Ensure it's initialized so $table_name is set.

            // Get all coupon logs from your custom table
            $coupon_logs = BirthdayBash_DB::get_all_coupon_logs( array(
                'limit'  => 20, // You might want to implement pagination for more entries
                'offset' => 0,
                'order_by' => 'coupon_generation_date',
                'order'    => 'DESC',
            ) );

            if ( ! empty( $coupon_logs ) ) {
                echo '<table class="wp-list-table widefat fixed striped">';
                // Update table headers to include Redeemed Date and Order ID
                echo '<thead><tr>';
                echo '<th>' . esc_html__( 'Coupon Code', 'birthday-bash' ) . '</th>';
                echo '<th>' . esc_html__( 'Recipient', 'birthday-bash' ) . '</th>';
                echo '<th>' . esc_html__( 'Issue Date', 'birthday-bash' ) . '</th>';
                echo '<th>' . esc_html__( 'Expires', 'birthday-bash' ) . '</th>';
                echo '<th>' . esc_html__( 'Redeemed Date', 'birthday-bash' ) . '</th>'; // New header
                echo '<th>' . esc_html__( 'Order ID', 'birthday-bash' ) . '</th>';    // New header
                echo '</tr></thead>';
                echo '<tbody>';

                foreach ( $coupon_logs as $log_entry ) {
                    // Data directly from your custom log table
                    $coupon_id        = (int) $log_entry->coupon_id;
                    $coupon_code      = esc_html( $log_entry->coupon_code );
                    $user_id          = (int) $log_entry->user_id;
                    $issue_date_raw   = $log_entry->coupon_generation_date;
                    $redeemed_date_raw = $log_entry->coupon_redeemed_date;
                    $order_id_from_log = (int) $log_entry->order_id; // Will be 0 if NULL in DB

                    $user_info = get_userdata( $user_id );
                    
                    // Retrieve WC_Coupon object to get expiry date
                    $coupon_obj = new WC_Coupon( $coupon_id );
                    $expiry_date = $coupon_obj->get_date_expires(); // Returns WC_DateTime object or null
                    $expiry_date_display = $expiry_date ? date_i18n( wc_date_format(), $expiry_date->getTimestamp() ) : esc_html__( 'Never', 'birthday-bash' );

                    $recipient_name = '';
                    if ( $user_info ) {
                        $recipient_name = '<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '">' . esc_html( $user_info->display_name ) . '</a>';
                    } else {
                        // In your DB structure, user_id is NOT NULL, so it should always be a registered user.
                        // However, if it could ever be 0 or non-existent, provide a fallback.
                        $recipient_name = esc_html__( 'Unknown User', 'birthday-bash' );
                    }

                    $issue_date_display = $issue_date_raw ? date_i18n( wc_date_format(), strtotime( $issue_date_raw ) ) : esc_html__( 'N/A', 'birthday-bash' );
                    
                    // Format redeemed date
                    $redeemed_date_display = esc_html__( 'Not Redeemed', 'birthday-bash' );
                    if ( $redeemed_date_raw && $redeemed_date_raw !== '0000-00-00 00:00:00' ) {
                        $redeemed_date_display = date_i18n( wc_date_format(), strtotime( $redeemed_date_raw ) );
                    }

                    // Format order ID
                    $order_id_display = esc_html__( 'N/A', 'birthday-bash' );
                    if ( $order_id_from_log > 0 ) {
                        $order = wc_get_order( $order_id_from_log );
                        if ( $order ) {
                            $order_id_display = '<a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( $order->get_order_number() ) . '</a>';
                        } else {
                            $order_id_display = esc_html( $order_id_from_log ) . ' (' . esc_html__( 'Order Not Found', 'birthday-bash' ) . ')';
                        }
                    }

                    echo '<tr>';
                    echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $coupon_id . '&action=edit' ) ) . '">' . esc_html( $coupon_code ) . '</a></td>';
                    echo '<td>' . wp_kses_post( $recipient_name ) . '</td>';
                    echo '<td>' . esc_html( $issue_date_display ) . '</td>';
                    echo '<td>' . esc_html( $expiry_date_display ) . '</td>';
                    echo '<td>' . wp_kses_post( $redeemed_date_display ) . '</td>'; // New column
                    echo '<td>' . wp_kses_post( $order_id_display ) . '</td>';      // New column
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                // wp_reset_postdata() is not needed here as we are not using WP_Query loop.
            } else {
                echo '<p>' . esc_html__( 'No birthday coupons have been issued yet.', 'birthday-bash' ) . '</p>';
            }
            ?>
        </div>
        <?php
    }
}