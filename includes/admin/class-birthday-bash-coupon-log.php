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
            // Retrieve coupons with meta indicating they are birthday coupons
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This query runs in the admin and uses the standard WP_Query API for meta_query.
            $args = array(
                'posts_per_page' => 20,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_type'      => 'shop_coupon',
                'post_status'    => 'publish',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This query runs in the admin and uses the standard WP_Query API for meta_query.
                'meta_query'     => array(
                    array(
                        'key'     => '_birthday_bash_coupon',
                        'value'   => 'yes',
                        'compare' => '=',
                    ),
                ),
            );

            $birthday_coupons = new WP_Query( $args );

            if ( $birthday_coupons->have_posts() ) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>' . esc_html__( 'Coupon Code', 'birthday-bash' ) . '</th><th>' . esc_html__( 'Recipient', 'birthday-bash' ) . '</th><th>' . esc_html__( 'Issue Date', 'birthday-bash' ) . '</th><th>' . esc_html__( 'Expires', 'birthday-bash' ) . '</th></tr></thead>';
                echo '<tbody>';
                while ( $birthday_coupons->have_posts() ) {
                    $birthday_coupons->the_post();
                    $coupon_id   = get_the_ID();
                    $coupon_code = get_the_title();
                    $user_id     = get_post_meta( $coupon_id, '_birthday_bash_user_id', true );
                    $user_info   = get_userdata( $user_id );
                    $issue_date  = get_post_meta( $coupon_id, '_birthday_bash_issue_date', true );
                    $expiry_date = get_post_meta( $coupon_id, 'date_expires', true ) ? date_i18n( wc_date_format(), get_post_meta( $coupon_id, 'date_expires', true ) ) : esc_html__( 'Never', 'birthday-bash' );

                    $recipient_name = ''; // Initialize
                    if ( $user_info ) {
                        $recipient_name = '<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '">' . esc_html( $user_info->display_name ) . '</a>';
                    } else {
                        // For guest users, try to retrieve email from a custom meta if stored
                        $recipient_email = get_post_meta( $coupon_id, '_birthday_bash_guest_email', true );
                        if ( $recipient_email ) {
                            $recipient_name = esc_html( $recipient_email ) . ' (' . esc_html__( 'Guest', 'birthday-bash' ) . ')';
                        } else {
                            $recipient_name = esc_html__( 'Guest User', 'birthday-bash' ); // Fallback if no email is stored for guest
                        }
                    }

                    echo '<tr>';
                    echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $coupon_id . '&action=edit' ) ) . '">' . esc_html( $coupon_code ) . '</a></td>';
                    echo '<td>' . wp_kses_post( $recipient_name ) . '</td>';
                    echo '<td>' . esc_html( $issue_date ? date_i18n( wc_date_format(), strtotime( $issue_date ) ) : esc_html__( 'N/A', 'birthday-bash' ) ) . '</td>';
                    echo '<td>' . esc_html( $expiry_date ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                wp_reset_postdata();
            } else {
                echo '<p>' . esc_html__( 'No birthday coupons have been issued yet.', 'birthday-bash' ) . '</p>';
            }
            ?>
        </div>
        <?php
    }
}