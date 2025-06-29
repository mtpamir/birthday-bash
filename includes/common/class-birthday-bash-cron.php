<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Cron
 *
 * Handles cron jobs for sending birthday coupons.
 */
class BirthdayBash_Cron {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action( 'wp', array( $this, 'schedule_daily_cron_job' ) );
        add_action( 'birthday_bash_daily_cron', array( $this, 'do_daily_birthday_check' ) );
    }

    /**
     * Schedule the daily cron job.
     */
    public function schedule_daily_cron_job() {
        if ( ! wp_next_scheduled( 'birthday_bash_daily_cron' ) ) {
            // Schedule for tomorrow 00:00:00 in WordPress timezone
            $midnight_tomorrow = strtotime( 'tomorrow', current_time( 'timestamp' ) );
            wp_schedule_event( $midnight_tomorrow, 'daily', 'birthday_bash_daily_cron' );
        }
    }

    /**
     * Perform daily birthday check and send coupons.
     */
    public function do_daily_birthday_check() {
        $days_before_birthday = 7; // Send coupon 7 days before

        // Get today's timestamp in WordPress's configured timezone.
        $today_timestamp = current_time( 'timestamp' );
        // Calculate the target date for coupon sending (7 days from today)
        $coupon_send_timestamp = strtotime( '+' . $days_before_birthday . ' days', $today_timestamp );
        // Format this timestamp into MM-DD using wp_date for timezone safety.
        $coupon_send_date = wp_date( 'm-d', $coupon_send_timestamp );

        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This query runs on a daily cron and is the standard way to query user meta.
        $users = get_users( array(
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This query runs on a daily cron and is the standard way to query user meta.
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'birthday_bash_birthday_day',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => 'birthday_bash_birthday_month',
                    'compare' => 'EXISTS',
                ),
            ),
            'fields'     => array( 'ID', 'user_email', 'display_name' ),
        ) );

        foreach ( $users as $user ) {
            $birthday_day   = (int) get_user_meta( $user->ID, 'birthday_bash_birthday_day', true );
            $birthday_month = (int) get_user_meta( $user->ID, 'birthday_bash_birthday_month', true );

            // Format for comparison 'MM-DD'
            $user_birthday_md = sprintf( '%02d-%02d', $birthday_month, $birthday_day );

            if ( $user_birthday_md === $coupon_send_date ) {
                // Check if coupon already issued for this user for current year's birthday
                $current_year_for_meta = wp_date( 'Y' );
                $issued_this_year = get_user_meta( $user->ID, '_birthday_bash_coupon_issued_' . $current_year_for_meta, true );
                if ( $issued_this_year ) {
                    continue; // Coupon already issued for this year
                }

                // Check if user has unsubscribed
                $unsubscribed = get_user_meta( $user->ID, 'birthday_bash_unsubscribed', true );
                if ( $unsubscribed ) {
                    continue; // User has unsubscribed
                }

                // Generate and send coupon
                $coupon_code = BirthdayBash_Helper::generate_unique_coupon_code();
                $coupon_id   = BirthdayBash_Helper::create_woocommerce_coupon( $coupon_code, $user->ID );

                if ( $coupon_id ) {
                    // Update user meta to indicate coupon issued for this year
                    update_user_meta( $user->ID, '_birthday_bash_coupon_issued_' . $current_year_for_meta, time() );

                    // Log coupon issuance in custom table
                    BirthdayBash_DB::insert_coupon_log( $coupon_id, $user->ID, $user_birthday_md, current_time( 'mysql' ), $coupon_code );

                    // Send email
                    BirthdayBash_Email::send_birthday_coupon_email( $user->ID, $coupon_id, $coupon_code );
                }
            }
        }
    }
}