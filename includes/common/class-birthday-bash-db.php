<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class BirthdayBash_DB {

    protected static $instance = null;
    private static $table_name = '';
    private static $cache_group = 'birthday_bash_coupons';

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
    }

    public static function create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'birthday_bash_coupons';
        $charset_collate = $wpdb->get_charset_collate();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant, not user input.
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) NOT NULL,
            coupon_code varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            user_birthday varchar(5) NOT NULL COMMENT 'MM-DD format',
            coupon_generation_date datetime NOT NULL,
            coupon_redeemed_date datetime DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY coupon_id (coupon_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta is the standard WordPress function for schema changes.
        dbDelta( $sql );
    }

    public static function insert_coupon_log( $coupon_id, $user_id, $user_birthday, $generation_date, $coupon_code = '' ) {
        global $wpdb;

        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        if ( empty( $coupon_code ) ) {
            $coupon = new WC_Coupon( $coupon_id );
            $coupon_code = $coupon->get_code();
        }

        $data = array(
            'coupon_id'              => $coupon_id,
            'coupon_code'            => $coupon_code,
            'user_id'                => $user_id,
            'user_birthday'          => $user_birthday,
            'coupon_generation_date' => $generation_date,
        );

        $format = array( '%d', '%s', '%d', '%s', '%s' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->insert is the standard WordPress API for inserting data.
        $inserted = $wpdb->insert( self::$table_name, $data, $format );

        if ( $inserted ) {
            self::invalidate_user_coupon_cache( $user_id );
            self::invalidate_all_coupon_logs_cache();
        }

        return $inserted ? $wpdb->insert_id : false;
    }

    public static function update_coupon_log_redeemed( $coupon_id, $order_id, $redeemed_date ) {
        global $wpdb;

        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        $table = self::$table_name;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a constant and safe.
        $query = $wpdb->prepare( "SELECT user_id FROM {$table} WHERE coupon_id = %d", $coupon_id );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $user_id = $wpdb->get_var( $query );

        $data = array(
            'coupon_redeemed_date' => $redeemed_date,
            'order_id'             => $order_id,
        );

        $where = array( 'coupon_id' => $coupon_id );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Standard WordPress update call.
        $updated = $wpdb->update( $table, $data, $where, array( '%s', '%d' ), array( '%d' ) );

        if ( false !== $updated ) {
            if ( $user_id ) {
                self::invalidate_user_coupon_cache( $user_id );
            }
            self::invalidate_all_coupon_logs_cache();
        }

        return false !== $updated;
    }

    public static function get_user_issued_birthday_coupons( $user_id ) {
        global $wpdb;

        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        $cache_key = 'user_coupons_' . $user_id;
        $cached_results = wp_cache_get( $cache_key, self::$cache_group );

        if ( false !== $cached_results ) {
            return $cached_results;
        }

        $table = self::$table_name;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
        $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY coupon_generation_date DESC", $user_id );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results( $sql );

        wp_cache_set( $cache_key, $results, self::$cache_group, HOUR_IN_SECONDS );

        return $results;
    }

    public static function get_all_coupon_logs( $args = array() ) {
        global $wpdb;

        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        $defaults = array(
            'limit'    => 20,
            'offset'   => 0,
            'order_by' => 'coupon_generation_date',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $cache_key = 'all_coupon_logs_' . md5( serialize( $args ) );
        $cached_results = wp_cache_get( $cache_key, self::$cache_group );

        if ( false !== $cached_results ) {
            return $cached_results;
        }

        $allowed_order_by_cols = array( 'id', 'coupon_id', 'coupon_code', 'user_id', 'user_birthday', 'coupon_generation_date', 'coupon_redeemed_date', 'order_id' );
        $allowed_order_dirs = array( 'ASC', 'DESC' );

        $order_by_col = in_array( $args['order_by'], $allowed_order_by_cols, true ) ? $args['order_by'] : 'coupon_generation_date';
        $order_dir    = in_array( strtoupper( $args['order'] ), $allowed_order_dirs, true ) ? strtoupper( $args['order'] ) : 'DESC';

        $table = self::$table_name;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are sanitized through whitelisting.
        $query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY {$order_by_col} {$order_dir} LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results( $query );

        wp_cache_set( $cache_key, $results, self::$cache_group, HOUR_IN_SECONDS );

        return $results;
    }

    public static function get_total_coupon_logs_count() {
        global $wpdb;

        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        $cache_key = 'total_coupon_logs_count';
        $cached_count = wp_cache_get( $cache_key, self::$cache_group );

        if ( false !== $cached_count ) {
            return (int) $cached_count;
        }

        $table = self::$table_name;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
        $query = $wpdb->prepare( "SELECT COUNT(id) FROM {$table}" );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = (int) $wpdb->get_var( $query );

        wp_cache_set( $cache_key, $count, self::$cache_group, HOUR_IN_SECONDS );

        return $count;
    }

    private static function invalidate_user_coupon_cache( $user_id ) {
        wp_cache_delete( 'user_coupons_' . $user_id, self::$cache_group );
    }

    private static function invalidate_all_coupon_logs_cache() {
        wp_cache_delete( 'total_coupon_logs_count', self::$cache_group );
        wp_cache_delete( 'all_coupon_logs_' . md5( serialize( array() ) ), self::$cache_group ); // optional, clear general logs cache
    }
}
