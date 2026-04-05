<?php
/**
 * Cron-based reminder scheduling.
 *
 * Checks for upcoming events and queues reminders at configurable intervals.
 * Tracks sent reminders to prevent duplicates.
 * Never sends reminders for cancelled bookings or cancelled events.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Reminders {

    /**
     * @var string Reminders sent table.
     */
    private $table;

    public function __construct() {
        $this->table = SWS_Database::table( 'reminders_sent' );
    }

    /**
     * Check and send due reminders. Called by WP Cron every 15 minutes.
     */
    public function check_and_send() {
        global $wpdb;

        $intervals = $this->get_intervals();
        if ( empty( $intervals ) ) {
            return;
        }

        $bookings_table = SWS_Database::table( 'bookings' );
        $events_table   = SWS_Database::table( 'events' );
        $now            = current_time( 'timestamp' );

        foreach ( $intervals as $hours ) {
            $reminder_type = $hours . 'h';

            // Find confirmed bookings for published events happening in ~$hours from now.
            // Window: between ($hours - 0.5) and ($hours + 0.5) hours from now.
            $window_start = gmdate( 'Y-m-d H:i:s', $now + ( ( $hours - 0.5 ) * 3600 ) );
            $window_end   = gmdate( 'Y-m-d H:i:s', $now + ( ( $hours + 0.5 ) * 3600 ) );

            $bookings = $wpdb->get_results( $wpdb->prepare(
                "SELECT b.id AS booking_id
                 FROM {$bookings_table} b
                 INNER JOIN {$events_table} e ON b.event_id = e.id
                 WHERE b.status = 'confirmed'
                 AND e.status = 'published'
                 AND CONCAT(e.event_date, ' ', e.event_time_start) BETWEEN %s AND %s
                 AND b.id NOT IN (
                     SELECT r.booking_id FROM {$this->table} r WHERE r.reminder_type = %s
                 )",
                $window_start,
                $window_end,
                $reminder_type
            ) );

            foreach ( $bookings as $row ) {
                // Double-check not already sent (race condition guard).
                if ( $this->has_been_sent( $row->booking_id, $reminder_type ) ) {
                    continue;
                }

                // Send reminder.
                SWS_Emails::send_reminder( $row->booking_id, $reminder_type );

                // Record it.
                $this->mark_sent( $row->booking_id, $reminder_type );
            }
        }
    }

    /**
     * Check if a reminder has already been sent for a booking.
     *
     * @param int    $booking_id   Booking ID.
     * @param string $reminder_type Type identifier.
     * @return bool
     */
    private function has_been_sent( $booking_id, $reminder_type ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE booking_id = %d AND reminder_type = %s",
            $booking_id,
            $reminder_type
        ) );
    }

    /**
     * Record that a reminder was sent.
     *
     * @param int    $booking_id   Booking ID.
     * @param string $reminder_type Type identifier.
     */
    private function mark_sent( $booking_id, $reminder_type ) {
        global $wpdb;
        $wpdb->insert( $this->table, array(
            'booking_id'    => (int) $booking_id,
            'reminder_type' => sanitize_text_field( $reminder_type ),
        ) );
    }

    /**
     * Get configured reminder intervals (in hours).
     *
     * @return array Array of integers.
     */
    private function get_intervals() {
        $raw = get_option( 'sws_reminder_intervals', '48,24,2' );
        $values = array_map( 'trim', explode( ',', $raw ) );
        $values = array_filter( $values, 'is_numeric' );
        return array_map( 'intval', $values );
    }
}
