<?php
/**
 * Event CRUD and management.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Events {

    /**
     * @var string Table name.
     */
    private $table;

    public function __construct() {
        $this->table = SWS_Database::table( 'events' );
    }

    /**
     * Get a single event by ID.
     *
     * @param int $id Event ID.
     * @return object|null
     */
    public function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
    }

    /**
     * List events with pagination, search, and filtering.
     *
     * @param array $args Query arguments.
     * @return array { items: array, total: int }
     */
    public function list_events( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'search'   => '',
            'status'   => '',
            'upcoming' => false,
            'orderby'  => 'event_date',
            'order'    => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where   = array( '1=1' );
        $prepare = array();

        if ( ! empty( $args['search'] ) ) {
            $like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]   = '(title LIKE %s OR venue_name LIKE %s)';
            $prepare[] = $like;
            $prepare[] = $like;
        }

        if ( ! empty( $args['status'] ) ) {
            $where[]   = 'status = %s';
            $prepare[] = sanitize_text_field( $args['status'] );
        }

        if ( $args['upcoming'] ) {
            $where[] = "event_date >= CURDATE()";
            $where[] = "status = 'published'";
        }

        $where_clause = implode( ' AND ', $where );

        $allowed_orderby = array( 'event_date', 'title', 'created_at', 'ticket_price', 'capacity' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'event_date';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Count.
        $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_clause}";
        if ( ! empty( $prepare ) ) {
            $count_sql = $wpdb->prepare( $count_sql, ...$prepare );
        }
        $total = (int) $wpdb->get_var( $count_sql );

        // Fetch.
        $offset     = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
        $select_sql = "SELECT * FROM {$this->table}
                       WHERE {$where_clause}
                       ORDER BY {$orderby} {$order}
                       LIMIT %d OFFSET %d";

        $select_prepare = array_merge( $prepare, array( (int) $args['per_page'], $offset ) );
        $items = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$select_prepare ) );

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * Create a new event.
     *
     * @param array $data Event data.
     * @return int|false Event ID on success.
     */
    public function create( $data ) {
        global $wpdb;

        $data = $this->sanitize_event_data( $data );

        if ( empty( $data['title'] ) || empty( $data['event_date'] ) ) {
            return false;
        }

        $result = $wpdb->insert( $this->table, $data );

        if ( $result ) {
            $event_id = $wpdb->insert_id;

            /**
             * Fires after an event is created.
             *
             * @param int   $event_id Event ID.
             * @param array $data     Event data.
             */
            do_action( 'sws_event_created', $event_id, $data );

            return $event_id;
        }

        return false;
    }

    /**
     * Update an event.
     *
     * @param int   $id   Event ID.
     * @param array $data Fields to update.
     * @return bool
     */
    public function update( $id, $data ) {
        global $wpdb;

        $data   = $this->sanitize_event_data( $data );
        $result = $wpdb->update( $this->table, $data, array( 'id' => (int) $id ) );

        if ( $result !== false ) {
            do_action( 'sws_event_updated', $id, $data );
        }

        return $result !== false;
    }

    /**
     * Cancel an event. Updates status and triggers refunds/notifications.
     *
     * @param int $id Event ID.
     * @return bool
     */
    public function cancel( $id ) {
        global $wpdb;

        $event = $this->get( $id );
        if ( ! $event || $event->status === 'cancelled' ) {
            return false;
        }

        $result = $wpdb->update( $this->table, array( 'status' => 'cancelled' ), array( 'id' => (int) $id ) );

        if ( $result !== false ) {
            // Cancel all confirmed bookings and issue refunds.
            $bookings_table = SWS_Database::table( 'bookings' );
            $confirmed_bookings = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$bookings_table} WHERE event_id = %d AND status IN ('confirmed', 'waitlisted')",
                $id
            ) );

            foreach ( $confirmed_bookings as $booking ) {
                $update_data = array(
                    'status'       => 'cancelled',
                    'cancelled_at' => current_time( 'mysql' ),
                );

                // Trigger Stripe refund if payment was made.
                if ( ! empty( $booking->stripe_payment_intent_id ) && $booking->amount_paid > 0 ) {
                    $refunded = $this->refund_booking_payment( $booking );
                    if ( $refunded ) {
                        $update_data['status']      = 'refunded';
                        $update_data['refunded_at'] = current_time( 'mysql' );
                    }
                }

                $wpdb->update( $bookings_table, $update_data, array( 'id' => $booking->id ) );
            }

            /**
             * Fires after an event is cancelled.
             *
             * @param int    $id                Event ID.
             * @param object $event             Event data.
             * @param array  $confirmed_bookings Bookings that were cancelled.
             */
            do_action( 'sws_event_cancelled', $id, $event, $confirmed_bookings );
        }

        return $result !== false;
    }

    /**
     * Mark an event as completed.
     *
     * @param int $id Event ID.
     * @return bool
     */
    public function complete( $id ) {
        global $wpdb;

        $event = $this->get( $id );
        if ( ! $event ) {
            return false;
        }

        $result = $wpdb->update( $this->table, array( 'status' => 'completed' ), array( 'id' => (int) $id ) );

        if ( $result !== false ) {
            /**
             * Fires after an event is marked completed.
             * Admin can hook into this for no-show detection.
             *
             * @param int    $id    Event ID.
             * @param object $event Event data.
             */
            do_action( 'sws_event_completed', $id, $event );
        }

        return $result !== false;
    }

    /**
     * Duplicate an event (creates a new draft copy).
     *
     * @param int $id Source event ID.
     * @return int|false New event ID on success.
     */
    public function duplicate( $id ) {
        $event = $this->get( $id );
        if ( ! $event ) {
            return false;
        }

        $new_data = array(
            'title'                    => $event->title . ' (' . __( 'Copy', 'sws-members-club' ) . ')',
            'description'              => $event->description,
            'venue_name'               => $event->venue_name,
            'venue_address'            => $event->venue_address,
            'event_date'               => $event->event_date,
            'event_time_start'         => $event->event_time_start,
            'event_time_end'           => $event->event_time_end,
            'capacity'                 => $event->capacity,
            'ticket_price'             => $event->ticket_price,
            'currency'                 => $event->currency,
            'cancellation_cutoff_hours' => $event->cancellation_cutoff_hours,
            'waitlist_enabled'         => $event->waitlist_enabled,
            'status'                   => 'draft',
        );

        return $this->create( $new_data );
    }

    /**
     * Get event stats (bookings, waitlist, revenue, cancellations).
     *
     * @param int $id Event ID.
     * @return object
     */
    public function get_stats( $id ) {
        global $wpdb;

        $bookings_table = SWS_Database::table( 'bookings' );

        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END), 0) AS confirmed,
                COALESCE(SUM(CASE WHEN status = 'waitlisted' THEN 1 ELSE 0 END), 0) AS waitlisted,
                COALESCE(SUM(CASE WHEN status IN ('cancelled', 'refunded') THEN 1 ELSE 0 END), 0) AS cancelled,
                COALESCE(SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END), 0) AS no_shows,
                COALESCE(SUM(CASE WHEN status = 'confirmed' THEN amount_paid ELSE 0 END), 0) AS revenue
             FROM {$bookings_table}
             WHERE event_id = %d",
            $id
        ) );

        $event = $this->get( $id );
        $stats->capacity       = $event ? (int) $event->capacity : 0;
        $stats->spots_remaining = max( 0, $stats->capacity - (int) $stats->confirmed );

        return $stats;
    }

    /**
     * Sanitize event data for insert/update.
     *
     * @param array $data Raw data.
     * @return array Sanitized data.
     */
    private function sanitize_event_data( $data ) {
        $sanitized = array();

        $text_fields = array( 'title', 'venue_name', 'currency', 'status' );
        foreach ( $text_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
            }
        }

        if ( isset( $data['description'] ) ) {
            $sanitized['description'] = wp_kses_post( $data['description'] );
        }

        if ( isset( $data['venue_address'] ) ) {
            $sanitized['venue_address'] = sanitize_textarea_field( $data['venue_address'] );
        }

        if ( isset( $data['event_date'] ) ) {
            $sanitized['event_date'] = sanitize_text_field( $data['event_date'] );
        }

        if ( isset( $data['event_time_start'] ) ) {
            $sanitized['event_time_start'] = sanitize_text_field( $data['event_time_start'] );
        }

        if ( isset( $data['event_time_end'] ) ) {
            $sanitized['event_time_end'] = sanitize_text_field( $data['event_time_end'] );
        }

        $int_fields = array( 'capacity', 'cancellation_cutoff_hours', 'waitlist_enabled' );
        foreach ( $int_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitized[ $field ] = (int) $data[ $field ];
            }
        }

        if ( isset( $data['ticket_price'] ) ) {
            $sanitized['ticket_price'] = (float) $data['ticket_price'];
        }

        return $sanitized;
    }

    /**
     * Refund a booking payment via Stripe.
     *
     * @param object $booking Booking record.
     * @return bool
     */
    private function refund_booking_payment( $booking ) {
        $test_mode  = get_option( 'sws_stripe_test_mode', 1 );
        $secret_key = $test_mode
            ? get_option( 'sws_stripe_test_secret_key', '' )
            : get_option( 'sws_stripe_secret_key', '' );

        if ( empty( $secret_key ) ) {
            return false;
        }

        $response = wp_remote_post( 'https://api.stripe.com/v1/refunds', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
            ),
            'body'    => array(
                'payment_intent' => $booking->stripe_payment_intent_id,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body['id'] ) && ! isset( $body['error'] );
    }
}
