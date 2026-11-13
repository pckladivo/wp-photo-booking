<?php
/**
 * WP Photo Booking — Uninstall
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Removes all custom database tables and plugin options.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die('Forbidden');
}

global $wpdb;

// Drop custom tables
$tables = array(
    $wpdb->prefix . 'pb_activity',
    $wpdb->prefix . 'pb_email_log',
    $wpdb->prefix . 'pb_bookings',
    $wpdb->prefix . 'pb_blocked_dates',
    $wpdb->prefix . 'pb_forms',
);
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Remove plugin options
delete_option('wppb_settings');
delete_option('wppb_db_version');
delete_option('wppb_default_booking_form_id');

// Clear any scheduled cron events
wp_clear_scheduled_hook('wppb_daily_automation');
