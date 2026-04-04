<?php
/**
 * Plugin Name: SWS Members Club
 * Description: Membership billing and event ticketing management for a premium private members club.
 * Version: 1.0.0
 * Author: SWS
 * Text Domain: sws-members-club
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SWS_PLUGIN_VERSION', '1.0.0' );
define( 'SWS_PLUGIN_FILE', __FILE__ );
define( 'SWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SWS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload plugin classes.
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'SWS_';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $class_file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
    $file       = SWS_PLUGIN_DIR . 'includes/' . $class_file;

    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

/**
 * Run on plugin activation.
 */
function sws_activate() {
    // Create / update database tables.
    $database = new SWS_Database();
    $database->create_tables();

    // Seed default membership tiers.
    $tiers = new SWS_Tiers();
    $tiers->seed_defaults();

    // Register member role.
    sws_register_member_role();

    // Schedule cron jobs.
    if ( ! wp_next_scheduled( 'sws_daily_stripe_sync' ) ) {
        wp_schedule_event( time(), 'daily', 'sws_daily_stripe_sync' );
    }
    if ( ! wp_next_scheduled( 'sws_check_reminders' ) ) {
        wp_schedule_event( time(), 'fifteen_minutes', 'sws_check_reminders' );
    }
    if ( ! wp_next_scheduled( 'sws_check_waitlist_expiry' ) ) {
        wp_schedule_event( time(), 'fifteen_minutes', 'sws_check_waitlist_expiry' );
    }

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sws_activate' );

/**
 * Run on plugin deactivation.
 */
function sws_deactivate() {
    wp_clear_scheduled_hook( 'sws_daily_stripe_sync' );
    wp_clear_scheduled_hook( 'sws_check_reminders' );
    wp_clear_scheduled_hook( 'sws_check_waitlist_expiry' );
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sws_deactivate' );

/**
 * Register the sws_member user role.
 */
function sws_register_member_role() {
    if ( ! get_role( 'sws_member' ) ) {
        add_role( 'sws_member', __( 'SWS Member', 'sws-members-club' ), array(
            'read' => true,
        ) );
    }
}

/**
 * Add custom cron interval: every 15 minutes.
 */
function sws_cron_schedules( $schedules ) {
    if ( ! isset( $schedules['fifteen_minutes'] ) ) {
        $schedules['fifteen_minutes'] = array(
            'interval' => 900,
            'display'  => __( 'Every 15 Minutes', 'sws-members-club' ),
        );
    }
    return $schedules;
}
add_filter( 'cron_schedules', 'sws_cron_schedules' );

/**
 * Initialize plugin on plugins_loaded.
 */
function sws_init() {
    // Ensure role exists (in case it was removed).
    sws_register_member_role();

    // Load admin.
    if ( is_admin() ) {
        new SWS_Admin();
    }
}
add_action( 'plugins_loaded', 'sws_init' );

/**
 * Hook: daily Stripe subscription sync.
 */
add_action( 'sws_daily_stripe_sync', function () {
    $members = new SWS_Members();
    $members->sync_all_stripe_subscriptions();
} );
