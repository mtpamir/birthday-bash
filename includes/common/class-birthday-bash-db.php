<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_DB
 *
 * Handles database interactions for the free plugin (custom table).
 */
class BirthdayBash_DB {

    protected static $instance = null;
    private static $table_name = ''; // Will still be declared as empty initially
    private static $cache_group = 'birthday_bash_coupons'; // Define a cache group

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        global $wpdb;
        // This constructor will initialize self::$table_name when get_instance() is first called.
        // For static methods called before get_instance(), it needs to be initialized there directly.
        self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
    }

    /**
     * Create custom database table for birthday coupons.
     */
    public static function create_tables() {
        global $wpdb;

        // FIXED: Ensure $table_name is initialized here, as this static method might run before the constructor.
        $table_name = $wpdb->prefix . 'birthday_bash_coupons';
        
        $charset_collate = $wpdb->get_charset_collate();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant, not user input.
        $sql = "CREATE TABLE " . $table_name . " (
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

    /**
     * Insert a new birthday coupon log entry.
     *
     * @param int $coupon_id
     * @param int $user_id
     * @param string $user_birthday (MM-DD format)
     * @param string $generation_date (YYYY-MM-DD HH:MM:SS)
     * @param string $coupon_code
     * @return int|bool Insert ID on success, false on failure.
     */
    public static function insert_coupon_log( $coupon_id, $user_id, $user_birthday, $generation_date, $coupon_code = '' ) {
        global $wpdb;

        // Ensure table name is set for any static call that might precede constructor run.
        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        if ( empty( $coupon_code ) ) {
            $coupon = new WC_Coupon( $coupon_id );
            $coupon_code = $coupon->get_code();
        }

        $data = array(
            'coupon_id'            => $coupon_id,
            'coupon_code'          => $coupon_code,
            'user_id'              => $user_id,
            'user_birthday'        => $user_birthday,
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

    /**
     * Update a birthday coupon log entry when redeemed.
     *
     * @param int $coupon_id
     * @param int $order_id
     * @param string $redeemed_date (YYYY-MM-DD HH:MM:SS)
     * @return bool
     */
    public static function update_coupon_log_redeemed( $coupon_id, $order_id, $redeemed_date ) {
        global $wpdb;

        // Ensure table name is set for any static call that might precede constructor run.
        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        // Get the user_id associated with this coupon_id for cache invalidation
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Small internal lookup for cache invalidation.
        $user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM " . self::$table_name . " WHERE coupon_id = %d", $coupon_id ) );


        $data = array(
            'coupon_redeemed_date' => $redeemed_date,
            'order_id'             => $order_id,
        );

        $where = array( 'coupon_id' => $coupon_id );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->update is the standard WordPress API for updating data.
        $updated = $wpdb->update( self::$table_name, $data, $where, array( '%s', '%d' ), array( '%d' ) );

        if ( false !== $updated ) {
            if ( $user_id ) {
                self::invalidate_user_coupon_cache( $user_id );
            }
            self::invalidate_all_coupon_logs_cache();
        }

        return false !== $updated;
    }

    /**
     * Get issued birthday coupons for a specific user.
     *
     * @param int $user_id
     * @return array
     */
    public static function get_user_issued_birthday_coupons( $user_id ) {
        global $wpdb;

        // Ensure table name is set for any static call that might precede constructor run.
        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        $cache_key = 'user_coupons_' . $user_id;
        $cached_results = wp_cache_get( $cache_key, self::$cache_group );

        if ( false !== $cached_results ) {
            return $cached_results;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching handled manually.
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE user_id = %d ORDER BY coupon_generation_date DESC",
            $user_id
        ) );

        wp_cache_set( $cache_key, $results, self::$cache_group, HOUR_IN_SECONDS ); // Cache for 1 hour

        return $results;
    }

    /**
     * Get all birthday coupon logs.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_all_coupon_logs( $args = array() ) {
        global $wpdb;

        // Ensure table name is set for any static call that might precede constructor run.
        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        $defaults = array(
            'limit'  => 20,
            'offset' => 0,
            'order_by' => 'coupon_generation_date',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $cache_key = 'all_coupon_logs_' . md5( serialize( $args ) ); // Cache key depends on query args
        $cached_results = wp_cache_get( $cache_key, self::$cache_group );

        if ( false !== $cached_results ) {
            return $cached_results;
        }

        // Whitelist allowed order by columns to prevent SQL injection.
        $allowed_order_by_cols = array( 'id', 'coupon_id', 'coupon_code', 'user_id', 'user_birthday', 'coupon_generation_date', 'coupon_redeemed_date', 'order_id' );
        $allowed_order_dirs = array( 'ASC', 'DESC' );

        $order_by_col = in_array( $args['order_by'], $allowed_order_by_cols ) ? $args['order_by'] : 'coupon_generation_date';
        $order_dir    = in_array( strtoupper( $args['order'] ), $allowed_order_dirs ) ? strtoupper( $args['order'] ) : 'DESC';

        // Construct the query string. Table name cannot be a placeholder.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant. Order by columns are whitelisted.
        $sql = "SELECT * FROM " . self::$table_name . " ORDER BY {$order_by_col} {$order_dir}";
        // Prepare LIMIT and OFFSET.
        $sql = $wpdb->prepare( $sql . " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query variable is result of $wpdb->prepare().
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching handled manually.
        $results = $wpdb->get_results( $sql );

        wp_cache_set( $cache_key, $results, self::$cache_group, HOUR_IN_SECONDS ); // Cache for 1 hour

        return $results;
    }

    /**
     * Get total count of birthday coupon logs.
     *
     * @return int
     */
    public static function get_total_coupon_logs_count() {
        global $wpdb;

        // Ensure table name is set for any static call that might precede constructor run.
        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'birthday_bash_coupons';
        }

        $cache_key = 'total_coupon_logs_count';
        $cached_count = wp_cache_get( $cache_key, self::$cache_group );

        if ( false !== $cached_count ) {
            return (int) $cached_count;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching handled manually.
        $count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM " . self::$table_name );

        wp_cache_set( $cache_key, $count, self::$cache_group, HOUR_IN_SECONDS ); // Cache for 1 hour

        return $count;
    }

    /**
     * Invalidates the cache for a specific user's coupons.
     *
     * @param int $user_id The ID of the user whose cache to invalidate.
     */
    private static function invalidate_user_coupon_cache( $user_id ) {
        wp_cache_delete( 'user_coupons_' . $user_id, self::$cache_group );
    }

    /**
     * Invalidates the cache for all general coupon logs and total count.
     * This should be called whenever any coupon log is inserted, updated, or deleted.
     */
    private static function invalidate_all_coupon_logs_cache() {
        wp_cache_delete( 'total_coupon_logs_count', self::$cache_group );

        // A more robust solution for get_all_coupon_logs would be to clear all keys related to
        // that query or use a cache versioning system if needed for very high accuracy.
        // For now, relies on the 1-hour expiration and user-specific invalidation.
    }
}

// Hook to update coupon log when a coupon is used
add_action( 'woocommerce_applied_coupon', 'birthday_bash_update_coupon_log_on_apply', 10, 1 );
function birthday_bash_update_coupon_log_on_apply( $coupon_code ) {
    $coupon_id = wc_get_coupon_id_by_code( $coupon_code );
    if ( $coupon_id ) {
        $is_birthday_coupon = get_post_meta( $coupon_id, '_birthday_bash_coupon', true );
        if ( 'yes' === $is_birthday_coupon ) {
            // Store coupon ID in session to retrieve on order completion
            WC()->session->set( 'birthday_bash_applied_coupon_id', $coupon_id );
        }
    }
}

add_action( 'woocommerce_checkout_order_processed', 'birthday_bash_update_coupon_log_on_order_processed', 10, 3 );
function birthday_bash_update_coupon_log_on_order_processed( $order_id, $data, $order ) {
    // Retrieve the coupon ID from session and ensure it's valid
    $coupon_id = WC()->session->get( 'birthday_bash_applied_coupon_id' );
    if ( $coupon_id ) {
        $coupon_obj = new WC_Coupon( $coupon_id );

        // Ensure the coupon actually belongs to the order AND that it's a valid birthday coupon.
        // Use $order->get_coupon_codes() which returns an array of applied coupon codes.
        if ( in_array( $coupon_obj->get_code(), $order->get_coupon_codes() ) && get_post_meta( $coupon_id, '_birthday_bash_coupon', true ) === 'yes' ) {
            BirthdayBash_DB::update_coupon_log_redeemed( $coupon_id, $order_id, current_time( 'mysql' ) );
            WC()->session->set( 'birthday_bash_applied_coupon_id', null ); // Clear session
        }
    }
}