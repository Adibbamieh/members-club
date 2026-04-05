<?php
/**
 * Booking logic: create bookings, cancel, refund, validate.
 *
 * Each ticket is an individual row — cancelling one never affects another.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Bookings {

    /**
     * @var string Table name.
     */
    private $table;

    public function __construct() {
        $this->table = SWS_Database::table( 'bookings' );
    }

    /**
     * Get a single booking by ID.
     *
     * @param int $id Booking ID.
     * @return object|null
     */
    public function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*, e.title AS event_title, e.event_date, e.event_time_start, e.event_time_end,
                    e.venue_name, e.venue_address, e.cancellation_cutoff_hours, e.status AS event_status
             FROM {$this->table} b
             LEFT JOIN " . SWS_Database::table( 'events' ) . " e ON b.event_id = e.id
             WHERE b.id = %d",
            $id
        ) );
    }

    /**
     * Get bookings for a member (upcoming and past).
     *
     * @param int    $user_id WordPress user ID.
     * @param string $time    'upcoming' or 'past'.
     * @return array
     */
    public function get_member_bookings( $user_id, $time = 'upcoming' ) {
        global $wpdb;

        $events_table = SWS_Database::table( 'events' );

        $date_condition = $time === 'upcoming'
            ? "e.event_date >= CURDATE()"
            : "e.event_date < CURDATE()";

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, e.title AS event_title, e.event_date, e.event_time_start, e.event_time_end,
                    e.venue_name, e.venue_address, e.cancellation_cutoff_hours, e.status AS event_status
             FROM {$this->table} b
             LEFT JOIN {$events_table} e ON b.event_id = e.id
             WHERE b.member_user_id = %d
             AND {$date_condition}
             AND b.status IN ('confirmed', 'waitlisted')
             ORDER BY e.event_date ASC, e.event_time_start ASC",
            $user_id
        ) );
    }

    /**
     * Get all bookings for an event (admin view).
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_event_bookings( $event_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, u.display_name, u.user_email
             FROM {$this->table} b
             LEFT JOIN {$wpdb->users} u ON b.member_user_id = u.ID
             WHERE b.event_id = %d
             ORDER BY b.booked_at ASC",
            $event_id
        ) );
    }

    /**
     * Count confirmed bookings for an event.
     *
     * @param int $event_id Event ID.
     * @return int
     */
    public function count_confirmed( $event_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE event_id = %d AND status = 'confirmed'",
            $event_id
        ) );
    }

    /**
     * Check if a member already has a confirmed booking for an event.
     *
     * @param int $event_id Event ID.
     * @param int $user_id  WordPress user ID.
     * @return bool
     */
    public function has_booking( $event_id, $user_id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE event_id = %d AND member_user_id = %d AND status = 'confirmed' AND is_guest_ticket = 0",
            $event_id,
            $user_id
        ) );
    }

    /**
     * Validate whether a member can book an event.
     *
     * @param int    $event_id Event ID.
     * @param int    $user_id  WordPress user ID.
     * @param int    $quantity 1 or 2 (with +1 guest).
     * @return true|WP_Error True if valid, WP_Error with reason if not.
     */
    public function validate_booking( $event_id, $user_id, $quantity = 1 ) {
        $events  = new SWS_Events();
        $members = new SWS_Members();

        $event  = $events->get( $event_id );
        $member = $members->get_by_user_id( $user_id );

        if ( ! $event ) {
            return new \WP_Error( 'event_not_found', __( 'Event not found.', 'sws-members-club' ) );
        }

        if ( $event->status !== 'published' ) {
            return new \WP_Error( 'event_not_available', __( 'This event is not available for booking.', 'sws-members-club' ) );
        }

        if ( ! $member ) {
            return new \WP_Error( 'not_a_member', __( 'You must be a member to book events.', 'sws-members-club' ) );
        }

        if ( $member->membership_status !== 'active' && $member->membership_status !== 'waitlist_only' ) {
            return new \WP_Error( 'membership_inactive', __( 'Your membership is not active. Please contact us to reactivate.', 'sws-members-club' ) );
        }

        if ( $member->membership_status === 'waitlist_only' ) {
            return new \WP_Error( 'waitlist_only', __( 'Your account is restricted to waitlist-only bookings due to penalty strikes.', 'sws-members-club' ) );
        }

        if ( $this->has_booking( $event_id, $user_id ) ) {
            return new \WP_Error( 'already_booked', __( 'You already have a booking for this event.', 'sws-members-club' ) );
        }

        $confirmed = $this->count_confirmed( $event_id );
        $available = (int) $event->capacity - $confirmed;

        if ( $available < $quantity ) {
            if ( $event->waitlist_enabled ) {
                return new \WP_Error( 'event_full_waitlist', __( 'This event is full. You can join the waitlist.', 'sws-members-club' ) );
            }
            return new \WP_Error( 'event_full', __( 'This event is fully booked.', 'sws-members-club' ) );
        }

        return true;
    }

    /**
     * Create a booking (one or two tickets).
     *
     * Handles payment logic based on member tier and event price.
     * Each ticket is a separate row.
     *
     * @param array $data {
     *     @type int    $event_id        Event ID.
     *     @type int    $user_id         WordPress user ID.
     *     @type bool   $include_guest   Whether to book a +1 guest.
     *     @type string $guest_name      Guest name (if +1).
     *     @type string $guest_email     Guest email (if +1).
     *     @type string $payment_intent_id Stripe PaymentIntent ID (if payment was taken).
     * }
     * @return array|WP_Error Array of booking IDs on success.
     */
    public function create_booking( $data ) {
        global $wpdb;

        $event_id      = (int) ( $data['event_id'] ?? 0 );
        $user_id       = (int) ( $data['user_id'] ?? 0 );
        $include_guest = (bool) ( $data['include_guest'] ?? false );
        $guest_name    = sanitize_text_field( $data['guest_name'] ?? '' );
        $guest_email   = sanitize_email( $data['guest_email'] ?? '' );
        $payment_intent_id = sanitize_text_field( $data['payment_intent_id'] ?? '' );

        $quantity   = $include_guest ? 2 : 1;
        $validation = $this->validate_booking( $event_id, $user_id, $quantity );

        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $events  = new SWS_Events();
        $members = new SWS_Members();
        $event   = $events->get( $event_id );
        $member  = $members->get_by_user_id( $user_id );

        // Determine price per ticket.
        $tiers = new SWS_Tiers();
        $events_included = $tiers->tier_includes_events( $member->membership_tier_id );

        if ( $events_included || (float) $event->ticket_price == 0 ) {
            $amount_per_ticket = 0.00;
            $payment_intent_id = '';
        } else {
            $amount_per_ticket = (float) $event->ticket_price;
        }

        $booking_ids = array();

        // Ticket 1: Member's own ticket.
        $wpdb->insert( $this->table, array(
            'event_id'                => $event_id,
            'member_user_id'          => $user_id,
            'guest_name'              => null,
            'guest_email'             => null,
            'is_guest_ticket'         => 0,
            'stripe_payment_intent_id' => $payment_intent_id ?: null,
            'amount_paid'             => $amount_per_ticket,
            'status'                  => 'confirmed',
            'booked_at'               => current_time( 'mysql' ),
        ) );
        $booking_ids[] = $wpdb->insert_id;

        // Ticket 2: Guest ticket (if applicable).
        if ( $include_guest ) {
            $wpdb->insert( $this->table, array(
                'event_id'                => $event_id,
                'member_user_id'          => $user_id,
                'guest_name'              => $guest_name,
                'guest_email'             => $guest_email,
                'is_guest_ticket'         => 1,
                'stripe_payment_intent_id' => $payment_intent_id ?: null,
                'amount_paid'             => $amount_per_ticket,
                'status'                  => 'confirmed',
                'booked_at'               => current_time( 'mysql' ),
            ) );
            $booking_ids[] = $wpdb->insert_id;
        }

        /**
         * Fires after booking(s) are created.
         *
         * @param array  $booking_ids Array of new booking IDs.
         * @param int    $event_id    Event ID.
         * @param int    $user_id     WordPress user ID.
         * @param object $event       Event object.
         */
        do_action( 'sws_booking_created', $booking_ids, $event_id, $user_id, $event );

        return $booking_ids;
    }

    /**
     * Cancel a single booking (individual ticket).
     *
     * @param int  $booking_id Booking ID.
     * @param int  $user_id    WordPress user ID (for permission check).
     * @param bool $admin      Whether this is an admin action (bypasses cutoff).
     * @return true|WP_Error
     */
    public function cancel_booking( $booking_id, $user_id = 0, $admin = false ) {
        global $wpdb;

        $booking = $this->get( $booking_id );

        if ( ! $booking ) {
            return new \WP_Error( 'booking_not_found', __( 'Booking not found.', 'sws-members-club' ) );
        }

        if ( $booking->status !== 'confirmed' ) {
            return new \WP_Error( 'invalid_status', __( 'This booking cannot be cancelled.', 'sws-members-club' ) );
        }

        // Permission check (member can only cancel their own).
        if ( ! $admin && $user_id && (int) $booking->member_user_id !== $user_id ) {
            return new \WP_Error( 'not_authorized', __( 'You are not authorized to cancel this booking.', 'sws-members-club' ) );
        }

        // Cancellation cutoff check (not for admin).
        if ( ! $admin ) {
            $can_cancel = $this->is_within_cancellation_window( $booking );
            if ( ! $can_cancel ) {
                return new \WP_Error( 'past_cutoff', __( 'The cancellation window has closed for this event.', 'sws-members-club' ) );
            }
        }

        $update_data = array(
            'status'       => 'cancelled',
            'cancelled_at' => current_time( 'mysql' ),
        );

        // Automatic Stripe refund — hard requirement from spec.
        if ( ! empty( $booking->stripe_payment_intent_id ) && $booking->amount_paid > 0 ) {
            $refunded = SWS_Stripe::refund( $booking->stripe_payment_intent_id );
            if ( $refunded ) {
                $update_data['status']      = 'refunded';
                $update_data['refunded_at'] = current_time( 'mysql' );
            }
        }

        $wpdb->update( $this->table, $update_data, array( 'id' => (int) $booking_id ) );

        /**
         * Fires after a booking is cancelled.
         *
         * @param int    $booking_id Booking ID.
         * @param object $booking    Booking data (before update).
         */
        do_action( 'sws_booking_cancelled', $booking_id, $booking );

        return true;
    }

    /**
     * Check if a booking is within the cancellation window.
     *
     * @param object $booking Booking with event data joined.
     * @return bool
     */
    public function is_within_cancellation_window( $booking ) {
        $cutoff_hours = (int) ( $booking->cancellation_cutoff_hours ?? get_option( 'sws_default_cancellation_cutoff', 48 ) );
        $event_start  = strtotime( $booking->event_date . ' ' . $booking->event_time_start );
        $cutoff_time  = $event_start - ( $cutoff_hours * 3600 );

        return current_time( 'timestamp' ) < $cutoff_time;
    }

    /**
     * Create a PaymentIntent for a booking (pre-payment step).
     *
     * @param int $event_id Event ID.
     * @param int $user_id  WordPress user ID.
     * @param int $quantity 1 or 2.
     * @return array|WP_Error { client_secret, payment_intent_id, amount }
     */
    public function create_payment_intent( $event_id, $user_id, $quantity = 1 ) {
        $events  = new SWS_Events();
        $members = new SWS_Members();

        $event  = $events->get( $event_id );
        $member = $members->get_by_user_id( $user_id );

        if ( ! $event || ! $member ) {
            return new \WP_Error( 'invalid', __( 'Invalid event or member.', 'sws-members-club' ) );
        }

        $tiers           = new SWS_Tiers();
        $events_included = $tiers->tier_includes_events( $member->membership_tier_id );

        // No payment needed.
        if ( $events_included || (float) $event->ticket_price == 0 ) {
            return array(
                'client_secret'    => '',
                'payment_intent_id' => '',
                'amount'           => 0,
                'free'             => true,
            );
        }

        $total = (float) $event->ticket_price * $quantity;

        $result = SWS_Stripe::create_payment_intent(
            $total,
            $event->currency ?: 'GBP',
            $member->stripe_customer_id ?: '',
            array(
                'event_id'  => $event_id,
                'user_id'   => $user_id,
                'quantity'  => $quantity,
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'client_secret'     => $result['client_secret'],
            'payment_intent_id' => $result['id'],
            'amount'            => $total,
            'free'              => false,
        );
    }

    /**
     * Admin: refund a booking with optional penalty.
     *
     * @param int    $booking_id  Booking ID.
     * @param bool   $add_penalty Whether to add a penalty strike.
     * @param string $reason      Penalty reason (no_show, late_cancellation).
     * @return true|WP_Error
     */
    public function admin_refund( $booking_id, $add_penalty = false, $reason = '' ) {
        $result = $this->cancel_booking( $booking_id, 0, true );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( $add_penalty && ! empty( $reason ) ) {
            $booking = $this->get( $booking_id );
            if ( $booking ) {
                $penalties = new SWS_Penalties();
                $penalties->add_strike( $booking->member_user_id, $booking->event_id, $reason );
            }
        }

        return true;
    }
}
