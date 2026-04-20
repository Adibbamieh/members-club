<?php
/**
 * Database table creation and management.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Database {

    /**
     * Create all custom tables. Safe to call multiple times (uses dbDelta).
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = $this->get_membership_tiers_schema( $charset_collate );
        dbDelta( $sql );

        $sql = $this->get_members_schema( $charset_collate );
        dbDelta( $sql );

        $sql = $this->get_events_schema( $charset_collate );
        dbDelta( $sql );

        $sql = $this->get_bookings_schema( $charset_collate );
        dbDelta( $sql );

        $sql = $this->get_penalties_schema( $charset_collate );
        dbDelta( $sql );

        $sql = $this->get_reminders_sent_schema( $charset_collate );
        dbDelta( $sql );

        update_option( 'sws_db_version', SWS_PLUGIN_VERSION );
    }

    /**
     * Membership tiers table.
     */
    private function get_membership_tiers_schema( $charset_collate ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sws_membership_tiers';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            events_included tinyint(1) NOT NULL DEFAULT 0,
            monthly_price decimal(10,2) NOT NULL DEFAULT 0.00,
            quarterly_price decimal(10,2) NOT NULL DEFAULT 0.00,
            annual_price decimal(10,2) NOT NULL DEFAULT 0.00,
            sort_order int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";
    }

    /**
     * Members table.
     */
    private function get_members_schema( $charset_collate ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sws_members';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            membership_tier_id bigint(20) unsigned NOT NULL,
            membership_status varchar(20) NOT NULL DEFAULT 'active',
            stripe_customer_id varchar(255) DEFAULT NULL,
            stripe_subscription_id varchar(255) DEFAULT NULL,
            billing_cycle varchar(20) NOT NULL DEFAULT 'monthly',
            membership_start_date date DEFAULT NULL,
            membership_end_date date DEFAULT NULL,
            penalty_strikes int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY membership_tier_id (membership_tier_id),
            KEY membership_status (membership_status),
            KEY stripe_customer_id (stripe_customer_id),
            KEY stripe_subscription_id (stripe_subscription_id)
        ) {$charset_collate};";
    }

    /**
     * Events table.
     */
    private function get_events_schema( $charset_collate ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sws_events';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description longtext DEFAULT NULL,
            venue_name varchar(255) DEFAULT NULL,
            venue_address text DEFAULT NULL,
            event_date date NOT NULL,
            event_time_start time NOT NULL,
            event_time_end time NOT NULL,
            capacity int(11) NOT NULL DEFAULT 0,
            ticket_price decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'GBP',
            cancellation_cutoff_hours int(11) NOT NULL DEFAULT 48,
            waitlist_enabled tinyint(1) NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY event_date (event_date),
            KEY status (status)
        ) {$charset_collate};";
    }

    /**
     * Bookings table.
     */
    private function get_bookings_schema( $charset_collate ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sws_bookings';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            member_user_id bigint(20) unsigned NOT NULL,
            guest_name varchar(255) DEFAULT NULL,
            guest_email varchar(255) DEFAULT NULL,
            is_guest_ticket tinyint(1) NOT NULL DEFAULT 0,
            stripe_payment_intent_id varchar(255) DEFAULT NULL,
            amount_paid decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(20) NOT NULL DEFAULT 'confirmed',
            waitlist_position int(11) DEFAULT NULL,
            waitlist_offered_at datetime DEFAULT NULL,
            waitlist_claim_token varchar(64) DEFAULT NULL,
            booked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cancelled_at datetime DEFAULT NULL,
            refunded_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY event_id (event_id),
            KEY member_user_id (member_user_id),
            KEY status (status),
            KEY waitlist_claim_token (waitlist_claim_token),
            KEY stripe_payment_intent_id (stripe_payment_intent_id)
        ) {$charset_collate};";
    }

    /**
     * Penalties table.
     */
    private function get_penalties_schema( $charset_collate ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sws_penalties';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_user_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned NOT NULL,
            reason varchar(30) NOT NULL,
            admin_note text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY member_user_id (member_user_id),
            KEY event_id (event_id)
        ) {$charset_collate};";
    }

    /**
     * Reminders sent tracking table.
     */
    private function get_reminders_sent_schema( $charset_collate ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sws_reminders_sent';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            reminder_type varchar(30) NOT NULL,
            sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY booking_reminder (booking_id,reminder_type),
            KEY booking_id (booking_id)
        ) {$charset_collate};";
    }

    /**
     * Get table name with prefix.
     */
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'sws_' . $name;
    }
}
