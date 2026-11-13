<?php
/**
 * Plugin Name: WP Photo Booking
 * Description: Configurable booking and contact forms for photographers, with calendar, status management, and email notifications.
 * Version:     1.10.0
 * Author:      Ulla Wolk
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-photo-booking
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 7.1
 */

if (!defined('ABSPATH')) die('Forbidden');

define('WPPB_VERSION',  '1.10.0');
define('WPPB_DIR',      plugin_dir_path(__FILE__));
define('WPPB_URL',      plugin_dir_url(__FILE__));

global $wpdb;
define('WPPB_TABLE_BOOKINGS',  $wpdb->prefix . 'pb_bookings');
define('WPPB_TABLE_BLOCKED',   $wpdb->prefix . 'pb_blocked_dates');
define('WPPB_TABLE_FORMS',     $wpdb->prefix . 'pb_forms');
define('WPPB_TABLE_ACTIVITY',  $wpdb->prefix . 'pb_activity');
define('WPPB_TABLE_EMAIL_LOG', $wpdb->prefix . 'pb_email_log');

require_once WPPB_DIR . 'includes/class-database.php';
require_once WPPB_DIR . 'includes/class-settings.php';
require_once WPPB_DIR . 'includes/class-booking.php';
require_once WPPB_DIR . 'includes/class-email.php';
require_once WPPB_DIR . 'includes/class-forms.php';
require_once WPPB_DIR . 'includes/class-form-renderer.php';
require_once WPPB_DIR . 'includes/class-form-handler.php';
require_once WPPB_DIR . 'includes/class-privacy.php';
require_once WPPB_DIR . 'includes/class-cron.php';
require_once WPPB_DIR . 'includes/class-questionnaire-frontend.php';

register_activation_hook(__FILE__, function () {
    WPPB_Database::upgrade();
    WPPB_Cron::schedule();
});

register_deactivation_hook(__FILE__, function () {
    WPPB_Cron::unschedule();
});

// Load translations
add_action('init', function () {
    load_plugin_textdomain('wp-photo-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Auto-upgrade DB when plugin version changes
add_action('plugins_loaded', function () {
    if (get_option('wppb_db_version') !== WPPB_VERSION) {
        WPPB_Database::upgrade();
    }
});

new WPPB_Form_Renderer();
new WPPB_Form_Handler();
new WPPB_Questionnaire_Frontend();

if (is_admin()) {
    require_once WPPB_DIR . 'admin/class-admin.php';
    new WPPB_Admin();
}
