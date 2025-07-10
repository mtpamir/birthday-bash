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
            wp_schedule_event( time(), 'daily', 'birthday_bash_daily_cron' );
        }
    }

    /**
     * Perform daily birthday check and send coupons.
     */
    public function do_daily_birthday_check() {
        if ( function_exists( 'WC' ) ) {
            WC()->mailer();
        } else {
            return;
        }

        if ( ! class_exists( 'BirthdayBash_Email' ) ) {
            require_once BIRTHDAY_BASH_PLUGIN_DIR . 'includes/common/class-birthday-bash-email.php';
        }

        $days_before_birthday = 7;
        $today_wp_datetime = new DateTime( current_time( 'mysql' ), wp_timezone() );
        $current_year      = (int) wp_date( 'Y' );

        $users = get_users( array(
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This query runs once daily via cron and is required to check user birthdays.
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

            if ( ! $birthday_day || ! $birthday_month ) {
                continue;
            }

            $this_year_birthday_date_str = sprintf( '%d-%02d-%02d', $current_year, $birthday_month, $birthday_day );
            $this_year_birthday_dt = DateTime::createFromFormat( 'Y-m-d', $this_year_birthday_date_str, wp_timezone() );

            if ( false === $this_year_birthday_dt ) {
                continue;
            }

            $upcoming_birthday_dt = clone $this_year_birthday_dt;
            $today_date_only = new DateTime( $today_wp_datetime->format('Y-m-d'), wp_timezone() );
            $upcoming_birthday_date_only = new DateTime( $upcoming_birthday_dt->format('Y-m-d'), wp_timezone() );

            if ( $upcoming_birthday_date_only < $today_date_only ) {
                $upcoming_birthday_dt->modify( '+1 year' );
            }

            $interval = $today_wp_datetime->diff( $upcoming_birthday_dt );
            $days_until_birthday = $interval->days;

            if ( $days_until_birthday < $days_before_birthday ) {
                $coupon_issued_meta_key = '_birthday_bash_coupon_issued_' . $current_year;
                $issued_this_year = get_user_meta( $user->ID, $coupon_issued_meta_key, true );

                if ( $issued_this_year ) {
                    continue;
                }

                $unsubscribed = get_user_meta( $user->ID, 'birthday_bash_unsubscribed', true );
                if ( $unsubscribed ) {
                    continue;
                }

                $coupon_code = BirthdayBash_Helper::generate_unique_coupon_code();
                $coupon_id   = BirthdayBash_Helper::create_woocommerce_coupon( $coupon_code, $user->ID );

                if ( $coupon_id ) {
                    update_user_meta( $user->ID, $coupon_issued_meta_key, time() );
                    $upcoming_birthday_md = $upcoming_birthday_dt->format( 'm-d' );
                    BirthdayBash_DB::insert_coupon_log( $coupon_id, $user->ID, $upcoming_birthday_md, current_time( 'mysql' ), $coupon_code );

                    $email_object = WC()->mailer()->get_emails();
                    $birthday_coupon_email_instance = $email_object['BirthdayBash_Birthday_Coupon'];

                    if ( $birthday_coupon_email_instance ) {
                        $coupon = new WC_Coupon( $coupon_id );
                        $user_data_obj = get_userdata( $user->ID );

                        $birthday_coupon_email_instance->object    = $user_data_obj;
                        $birthday_coupon_email_instance->recipient = $user_data_obj->user_email;

                        $birthday_coupon_email_instance->placeholders['{customer_name}']       = $user_data_obj->display_name;
                        $birthday_coupon_email_instance->placeholders['{coupon_code}']         = $coupon->get_code();
                        $birthday_coupon_email_instance->placeholders['{coupon_amount}']       = $coupon->get_amount();
                        $birthday_coupon_email_instance->placeholders['{coupon_type_text}']    = BirthdayBash_Helper::get_coupon_amount_text( $coupon );
                        $birthday_coupon_email_instance->placeholders['{coupon_expiry_date}']  = $coupon->get_date_expires() ? date_i18n( wc_date_format(), $coupon->get_date_expires()->getOffsetTimestamp() ) : esc_html__( 'Never', 'birthday-bash' );

                        WC()->mailer()->send(
                            $birthday_coupon_email_instance->recipient,
                            $birthday_coupon_email_instance->get_subject(),
                            $birthday_coupon_email_instance->get_content_html(),
                            $birthday_coupon_email_instance->get_headers(),
                            $birthday_coupon_email_instance->get_attachments()
                        );
                    }
                }
            }
        }
    }
}
