<?php
/**
 * REST API endpoint registration and handlers.
 *
 * All endpoints under /wp-json/sws/v1/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Rest_Api {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register all REST routes.
     */
    public function register_routes() {
        $namespace = 'sws/v1';

        // Events.
        register_rest_route( $namespace, '/events', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_events' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $namespace, '/events/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_event' ),
            'permission_callback' => '__return_true',
        ) );

        // Bookings.
        register_rest_route( $namespace, '/bookings', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_booking' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
        ) );

        register_rest_route( $namespace, '/bookings/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'cancel_booking' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
        ) );

        // Payment intent (pre-booking step).
        register_rest_route( $namespace, '/payment-intent', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_payment_intent' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
        ) );

        // My tickets.
        register_rest_route( $namespace, '/my-tickets', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_my_tickets' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
        ) );

        // Waitlist.
        register_rest_route( $namespace, '/waitlist/(?P<event_id>\d+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'join_waitlist' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
        ) );

        // Member status.
        register_rest_route( $namespace, '/member/status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_member_status' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
        ) );

        // Calendar .ics download (single booking).
        register_rest_route( $namespace, '/calendar/(?P<booking_id>\d+)\.ics', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'download_ics' ),
            'permission_callback' => '__return_true',
        ) );

        // Personal calendar subscription feed (all upcoming bookings).
        register_rest_route( $namespace, '/calendar/feed/(?P<token>[a-zA-Z0-9]+)\.ics', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'download_feed' ),
            'permission_callback' => '__return_true',
        ) );

        // Waitlist claim.
        register_rest_route( $namespace, '/waitlist/claim/(?P<token>[a-zA-Z0-9]+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'claim_waitlist' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
        ) );
    }

    /**
     * Permission: logged in.
     */
    public function is_logged_in() {
        return is_user_logged_in();
    }

    // -------------------------------------------------------------------------
    // Events
    // -------------------------------------------------------------------------

    public function get_events( $request ) {
        $events_model   = new SWS_Events();
        $bookings_model = new SWS_Bookings();

        $result = $events_model->list_events( array(
            'upcoming'  => true,
            'per_page'  => 50,
            'page'      => $request->get_param( 'page' ) ?: 1,
            'order'     => 'ASC',
        ) );

        $data = array();
        foreach ( $result['items'] as $event ) {
            $confirmed = $bookings_model->count_confirmed( $event->id );
            $data[] = $this->format_event( $event, $confirmed );
        }

        return rest_ensure_response( array(
            'events' => $data,
            'total'  => $result['total'],
        ) );
    }

    public function get_event( $request ) {
        $events_model   = new SWS_Events();
        $bookings_model = new SWS_Bookings();

        $event = $events_model->get( (int) $request['id'] );
        if ( ! $event ) {
            return new \WP_Error( 'not_found', __( 'Event not found.', 'sws-members-club' ), array( 'status' => 404 ) );
        }

        $confirmed = $bookings_model->count_confirmed( $event->id );

        return rest_ensure_response( $this->format_event( $event, $confirmed ) );
    }

    private function format_event( $event, $confirmed ) {
        $available = max( 0, (int) $event->capacity - $confirmed );

        return array(
            'id'                => (int) $event->id,
            'title'             => $event->title,
            'description'       => $event->description,
            'venue_name'        => $event->venue_name,
            'venue_address'     => $event->venue_address,
            'event_date'        => $event->event_date,
            'event_time_start'  => $event->event_time_start,
            'event_time_end'    => $event->event_time_end,
            'capacity'          => (int) $event->capacity,
            'spots_remaining'   => $available,
            'ticket_price'      => (float) $event->ticket_price,
            'currency'          => $event->currency,
            'waitlist_enabled'  => (bool) $event->waitlist_enabled,
            'is_full'           => $available <= 0,
        );
    }

    // -------------------------------------------------------------------------
    // Bookings
    // -------------------------------------------------------------------------

    public function create_payment_intent( $request ) {
        $user_id  = get_current_user_id();
        $event_id = (int) $request->get_param( 'event_id' );
        $quantity = (int) ( $request->get_param( 'quantity' ) ?: 1 );

        $bookings = new SWS_Bookings();
        $result   = $bookings->create_payment_intent( $event_id, $user_id, $quantity );

        if ( is_wp_error( $result ) ) {
            return new \WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
        }

        return rest_ensure_response( $result );
    }

    public function create_booking( $request ) {
        $user_id = get_current_user_id();

        $data = array(
            'event_id'          => (int) $request->get_param( 'event_id' ),
            'user_id'           => $user_id,
            'include_guest'     => (bool) $request->get_param( 'include_guest' ),
            'guest_name'        => $request->get_param( 'guest_name' ) ?: '',
            'guest_email'       => $request->get_param( 'guest_email' ) ?: '',
            'payment_intent_id' => $request->get_param( 'payment_intent_id' ) ?: '',
        );

        // If payment was required, verify it succeeded.
        if ( ! empty( $data['payment_intent_id'] ) ) {
            $confirmed = SWS_Stripe::confirm_payment( $data['payment_intent_id'] );
            if ( ! $confirmed ) {
                return new \WP_Error( 'payment_failed', __( 'Payment has not been confirmed.', 'sws-members-club' ), array( 'status' => 400 ) );
            }
        }

        $bookings = new SWS_Bookings();
        $result   = $bookings->create_booking( $data );

        if ( is_wp_error( $result ) ) {
            return new \WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'booking_ids' => $result,
            'message'     => __( 'Booking confirmed!', 'sws-members-club' ),
        ) );
    }

    public function cancel_booking( $request ) {
        $user_id    = get_current_user_id();
        $booking_id = (int) $request['id'];

        $bookings = new SWS_Bookings();
        $result   = $bookings->cancel_booking( $booking_id, $user_id );

        if ( is_wp_error( $result ) ) {
            return new \WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'message' => __( 'Booking cancelled successfully.', 'sws-members-club' ),
        ) );
    }

    // -------------------------------------------------------------------------
    // My Tickets
    // -------------------------------------------------------------------------

    public function get_my_tickets( $request ) {
        $user_id  = get_current_user_id();
        $bookings = new SWS_Bookings();

        $upcoming = $bookings->get_member_bookings( $user_id, 'upcoming' );
        $past     = $bookings->get_member_bookings( $user_id, 'past' );

        $format = function ( $booking ) {
            $bookings_model = new SWS_Bookings();
            return array(
                'id'               => (int) $booking->id,
                'event_id'         => (int) $booking->event_id,
                'event_title'      => $booking->event_title,
                'event_date'       => $booking->event_date,
                'event_time_start' => $booking->event_time_start,
                'event_time_end'   => $booking->event_time_end,
                'venue_name'       => $booking->venue_name,
                'venue_address'    => $booking->venue_address,
                'guest_name'       => $booking->guest_name,
                'is_guest_ticket'  => (bool) $booking->is_guest_ticket,
                'amount_paid'      => (float) $booking->amount_paid,
                'status'           => $booking->status,
                'can_cancel'       => $bookings_model->is_within_cancellation_window( $booking ),
                'booked_at'        => $booking->booked_at,
            );
        };

        return rest_ensure_response( array(
            'upcoming' => array_map( $format, $upcoming ),
            'past'     => array_map( $format, $past ),
        ) );
    }

    // -------------------------------------------------------------------------
    // Waitlist
    // -------------------------------------------------------------------------

    public function join_waitlist( $request ) {
        global $wpdb;

        $user_id  = get_current_user_id();
        $event_id = (int) $request['event_id'];

        $events     = new SWS_Events();
        $members    = new SWS_Members();
        $event      = $events->get( $event_id );
        $membership = $members->get_membership( $user_id );

        if ( ! $event || $event->status !== 'published' ) {
            return new \WP_Error( 'not_found', __( 'Event not found.', 'sws-members-club' ), array( 'status' => 404 ) );
        }

        // Active members (including those restricted to waitlist-only) may join.
        if ( ! $membership->is_active ) {
            return new \WP_Error( 'not_eligible', __( 'You need an active membership to join the waitlist.', 'sws-members-club' ), array( 'status' => 403 ) );
        }

        if ( ! $event->waitlist_enabled ) {
            return new \WP_Error( 'no_waitlist', __( 'Waitlist is not enabled for this event.', 'sws-members-club' ), array( 'status' => 400 ) );
        }

        // Check not already on waitlist.
        $bookings_table = SWS_Database::table( 'bookings' );
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table}
             WHERE event_id = %d AND member_user_id = %d AND status IN ('confirmed', 'waitlisted')",
            $event_id,
            $user_id
        ) );

        if ( $existing > 0 ) {
            return new \WP_Error( 'already_listed', __( 'You already have a booking or waitlist entry for this event.', 'sws-members-club' ), array( 'status' => 400 ) );
        }

        // Get next waitlist position.
        $max_position = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(waitlist_position) FROM {$bookings_table} WHERE event_id = %d AND status = 'waitlisted'",
            $event_id
        ) );

        $wpdb->insert( $bookings_table, array(
            'event_id'          => $event_id,
            'member_user_id'    => $user_id,
            'is_guest_ticket'   => 0,
            'amount_paid'       => 0.00,
            'status'            => 'waitlisted',
            'waitlist_position' => $max_position + 1,
            'booked_at'         => current_time( 'mysql' ),
        ) );

        $position = $max_position + 1;

        do_action( 'sws_waitlist_joined', $event_id, $user_id, $position );

        return rest_ensure_response( array(
            'message'  => sprintf(
                /* translators: %d: waitlist position */
                __( 'You have been added to the waitlist at position %d.', 'sws-members-club' ),
                $position
            ),
            'position' => $position,
        ) );
    }

    // -------------------------------------------------------------------------
    // Member Status
    // -------------------------------------------------------------------------

    public function get_member_status( $request ) {
        $user_id    = get_current_user_id();
        $members    = new SWS_Members();
        $membership = $members->get_membership( $user_id );

        return rest_ensure_response( array(
            'tier'             => $membership->tier_name,
            'tier_slug'        => $membership->tier_slug,
            'events_included'  => (bool) $membership->events_included,
            'is_active'        => (bool) $membership->is_active,
            'restricted'       => (bool) $membership->restricted,
            'can_book'         => (bool) $membership->can_book,
            'strikes'          => (int) $membership->strikes,
            'max_strikes'      => (int) get_option( 'sws_penalty_max_strikes', 3 ),
        ) );
    }

    // -------------------------------------------------------------------------
    // Calendar .ics Download
    // -------------------------------------------------------------------------

    public function download_ics( $request ) {
        $booking_id = (int) $request['booking_id'];
        $bookings   = new SWS_Bookings();
        $booking    = $bookings->get( $booking_id );

        if ( ! $booking ) {
            return new \WP_Error( 'not_found', __( 'Booking not found.', 'sws-members-club' ), array( 'status' => 404 ) );
        }

        $ics = SWS_Calendar::generate_ics( $booking );

        // Output raw ICS, bypassing WP_REST_Response JSON wrapping.
        // Apple Calendar/Outlook need raw text/calendar content, not a JSON-encoded string.
        if ( ! headers_sent() ) {
            nocache_headers();
            header( 'Content-Type: text/calendar; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="event-' . $booking->event_id . '.ics"' );
        }
        echo $ics;
        exit;
    }

    /**
     * Output a member's personal calendar subscription feed.
     *
     * Accessed by calendar apps via an unguessable per-member token (no WP auth),
     * so the token in the URL is the credential. Returns all upcoming CONFIRMED
     * bookings as a single auto-refreshing calendar.
     */
    public function download_feed( $request ) {
        $token   = sanitize_text_field( $request['token'] );
        $members = new SWS_Members();
        $member  = $members->get_by_calendar_token( $token );

        if ( ! $member ) {
            return new \WP_Error( 'not_found', __( 'Calendar not found.', 'sws-members-club' ), array( 'status' => 404 ) );
        }

        $bookings_model = new SWS_Bookings();
        $upcoming       = $bookings_model->get_member_bookings( $member->user_id, 'upcoming' );

        // Only confirmed bookings belong on a calendar (exclude waitlisted).
        $confirmed = array_filter( (array) $upcoming, function ( $b ) {
            return isset( $b->status ) && $b->status === 'confirmed';
        } );

        $ics = SWS_Calendar::generate_feed_ics( $confirmed );

        // Output raw ICS inline (no attachment) so calendar clients subscribe to it.
        if ( ! headers_sent() ) {
            nocache_headers();
            header( 'Content-Type: text/calendar; charset=utf-8' );
        }
        echo $ics;
        exit;
    }

    // -------------------------------------------------------------------------
    // Waitlist Claim
    // -------------------------------------------------------------------------

    public function claim_waitlist( $request ) {
        $token             = sanitize_text_field( $request['token'] );
        $payment_intent_id = $request->get_param( 'payment_intent_id' ) ?: '';

        $result = SWS_Waitlist::claim( $token, $payment_intent_id );

        if ( is_wp_error( $result ) ) {
            return new \WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'message'    => __( 'Your spot has been confirmed!', 'sws-members-club' ),
            'booking_id' => $result['booking_id'],
        ) );
    }
}
