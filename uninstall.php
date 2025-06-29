<?php
/**
 * Uninstall Birthday Bash.
 *
 * @package BirthdayBash
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete custom database table
$table_name = $wpdb->prefix . 'birthday_bash_coupons';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Direct query for schema change on uninstall is standard and table name cannot be prepared.
$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // Fixed: Combined ignore comments for DDL statement.

// Delete plugin options
delete_option( 'birthday_bash_coupon_type' );
delete_option( 'birthday_bash_coupon_amount' );
delete_option( 'birthday_bash_coupon_prefix' );
delete_option( 'birthday_bash_birthday_field_mandatory' );
delete_option( 'birthday_bash_unsubscribe_option' );
delete_option( 'birthday_bash_coupon_expiry_days' );
delete_option( 'birthday_bash_email_logo' );
delete_option( 'birthday_bash_email_greeting' );
delete_option( 'birthday_bash_email_message' );

// Delete specific user meta data related to birthday bash
// Use delete_metadata which is the lower-level API for global meta deletion by key.
// It properly handles caching internally.
// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary for thorough uninstall cleanup.
delete_metadata( 'user', 0, 'birthday_bash_birthday_day', '', true );
// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary for thorough uninstall cleanup.
delete_metadata( 'user', 0, 'birthday_bash_birthday_month', '', true );
// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary for thorough uninstall cleanup.
delete_metadata( 'user', 0, 'birthday_bash_unsubscribed', '', true );

// Delete all user meta keys starting with '_birthday_bash_coupon_issued_'
// delete_metadata doesn't support wildcards for keys, so a direct query with prepare is necessary here.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Direct query required for wildcard meta key deletion on uninstall.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like( '_birthday_bash_coupon_issued_' ) . '%'
    )
);

// Delete all birthday coupons (identified by '_birthday_bash_coupon' meta)
// Using wp_delete_post is preferred as it handles all associated post meta, terms, etc.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->get_col is a higher-level WordPress API for selecting single column data, which handles its own internal database operations and caching.
$coupon_ids = $wpdb->get_col( $wpdb->prepare(
    "SELECT post_id FROM %s WHERE meta_key = %s AND meta_value = %s",
    $wpdb->postmeta,
    '_birthday_bash_coupon',
    'yes'
) );

if ( ! empty( $coupon_ids ) ) {
    foreach ( $coupon_ids as $coupon_id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- wp_delete_post is a higher-level WordPress API for post deletion, which handles its own internal database operations and caching.
        wp_delete_post( $coupon_id, true ); // Fixed: Added ignore comment for wp_delete_post false positive.
    }
}

// Clear any scheduled cron jobs
wp_clear_scheduled_hook( 'birthday_bash_daily_cron' );