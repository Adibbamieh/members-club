<?php
/**
 * Frontend shortcode registration and rendering.
 *
 * Delegates to the template loader so theme devs can override output.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Shortcodes {

    public function __construct() {
        add_shortcode( 'sws_events', array( $this, 'render_events' ) );
        add_shortcode( 'sws_booking', array( $this, 'render_booking' ) );
        add_shortcode( 'sws_my_tickets', array( $this, 'render_my_tickets' ) );
        add_shortcode( 'sws_waitlist_claim', array( $this, 'render_waitlist_claim' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue frontend CSS and JS on pages with our shortcodes.
     */
    public function enqueue_assets() {
        // Always register; only enqueue when shortcode is present.
        wp_register_style(
            'sws-frontend',
            SWS_PLUGIN_URL . 'public/css/sws-frontend.css',
            array(),
            SWS_PLUGIN_VERSION
        );

        wp_register_script(
            'sws-frontend',
            SWS_PLUGIN_URL . 'public/js/sws-frontend.js',
            array(),
            SWS_PLUGIN_VERSION,
            true
        );

        // Stripe Elements (only loaded when needed).
        wp_register_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            array(),
            null,
            true
        );

        // Localize script data.
        wp_localize_script( 'sws-frontend', 'swsData', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'restUrl'        => rest_url( 'sws/v1/' ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'stripeKey'      => SWS_Stripe::get_publishable_key(),
            'i18n'           => array(
                'confirmCancel'  => __( 'Are you sure you want to cancel this ticket?', 'sws-members-club' ),
                'cancelRefund'   => __( 'You will receive a full refund.', 'sws-members-club' ),
                'cancelling'     => __( 'Cancelling...', 'sws-members-club' ),
                'booking'        => __( 'Processing...', 'sws-members-club' ),
                'error'          => __( 'An error occurred. Please try again.', 'sws-members-club' ),
                'bookNow'        => __( 'Book Now', 'sws-members-club' ),
                'cancelTicket'   => __( 'Cancel Ticket', 'sws-members-club' ),
            ),
        ) );
    }

    /**
     * Enqueue our assets (called from within shortcode render).
     */
    private function do_enqueue( $need_stripe = false ) {
        wp_enqueue_style( 'sws-frontend' );
        wp_enqueue_script( 'sws-frontend' );

        if ( $need_stripe && SWS_Stripe::is_configured() ) {
            wp_enqueue_script( 'stripe-js' );
        }
    }

    // -------------------------------------------------------------------------
    // [sws_events] — Events listing
    // -------------------------------------------------------------------------

    public function render_events( $atts ) {
        $this->do_enqueue();

        $events_model   = new SWS_Events();
        $bookings_model = new SWS_Bookings();
        $members_model  = new SWS_Members();

        $result = $events_model->list_events( array(
            'upcoming' => true,
            'per_page' => 50,
            'order'    => 'ASC',
        ) );

        $events = $result['items'];

        // Get member info for pricing display.
        $member           = null;
        $events_included  = false;
        if ( is_user_logged_in() ) {
            $member = $members_model->get_by_user_id( get_current_user_id() );
            if ( $member ) {
                $tiers           = new SWS_Tiers();
                $events_included = $tiers->tier_includes_events( $member->membership_tier_id );
            }
        }

        // Build event data with availability.
        $event_data = array();
        foreach ( $events as $event ) {
            $confirmed = $bookings_model->count_confirmed( $event->id );
            $event->spots_remaining = max( 0, (int) $event->capacity - $confirmed );
            $event->is_full         = $event->spots_remaining <= 0;
            $event_data[]           = $event;
        }

        return SWS_Template_Loader::render( 'events-listing.php', array(
            'events'          => $event_data,
            'member'          => $member,
            'events_included' => $events_included,
        ) );
    }

    // -------------------------------------------------------------------------
    // [sws_booking] — Single event booking form
    // -------------------------------------------------------------------------

    public function render_booking( $atts ) {
        $atts = shortcode_atts( array( 'event_id' => 0 ), $atts, 'sws_booking' );

        $event_id = (int) $atts['event_id'];
        if ( ! $event_id && isset( $_GET['event_id'] ) ) {
            $event_id = (int) $_GET['event_id'];
        }

        if ( ! $event_id ) {
            return '<p class="sws-notice">' . esc_html__( 'No event specified.', 'sws-members-club' ) . '</p>';
        }

        $this->do_enqueue( true );

        $events_model   = new SWS_Events();
        $bookings_model = new SWS_Bookings();
        $members_model  = new SWS_Members();

        $event = $events_model->get( $event_id );
        if ( ! $event || $event->status !== 'published' ) {
            return '<p class="sws-notice">' . esc_html__( 'This event is not available.', 'sws-members-club' ) . '</p>';
        }

        if ( ! is_user_logged_in() ) {
            return '<p class="sws-notice">' . esc_html__( 'Please log in to book this event.', 'sws-members-club' ) . '</p>';
        }

        $member = $members_model->get_by_user_id( get_current_user_id() );
        if ( ! $member ) {
            return '<p class="sws-notice">' . esc_html__( 'You must be a member to book events.', 'sws-members-club' ) . '</p>';
        }

        $tiers           = new SWS_Tiers();
        $events_included = $tiers->tier_includes_events( $member->membership_tier_id );
        $confirmed       = $bookings_model->count_confirmed( $event_id );
        $spots_remaining = max( 0, (int) $event->capacity - $confirmed );
        $already_booked  = $bookings_model->has_booking( $event_id, get_current_user_id() );
        $needs_payment   = ! $events_included && (float) $event->ticket_price > 0;
        $policy_url      = get_option( 'sws_policy_page_url', '' );

        return SWS_Template_Loader::render( 'booking-form.php', array(
            'event'           => $event,
            'member'          => $member,
            'events_included' => $events_included,
            'spots_remaining' => $spots_remaining,
            'already_booked'  => $already_booked,
            'needs_payment'   => $needs_payment,
            'policy_url'      => $policy_url,
        ) );
    }

    // -------------------------------------------------------------------------
    // [sws_my_tickets] — Member ticket dashboard
    // -------------------------------------------------------------------------

    public function render_my_tickets( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p class="sws-notice">' . esc_html__( 'Please log in to view your tickets.', 'sws-members-club' ) . '</p>';
        }

        $this->do_enqueue();

        $bookings_model = new SWS_Bookings();
        $members_model  = new SWS_Members();

        $user_id  = get_current_user_id();
        $member   = $members_model->get_by_user_id( $user_id );
        $upcoming = $bookings_model->get_member_bookings( $user_id, 'upcoming' );
        $past     = $bookings_model->get_member_bookings( $user_id, 'past' );

        $events_included = false;
        if ( $member ) {
            $tiers           = new SWS_Tiers();
            $events_included = $tiers->tier_includes_events( $member->membership_tier_id );
        }

        return SWS_Template_Loader::render( 'my-tickets.php', array(
            'upcoming'        => $upcoming,
            'past'            => $past,
            'member'          => $member,
            'events_included' => $events_included,
        ) );
    }

    /**
     * Build a Google Calendar URL for a booking.
     *
     * @param object $booking Booking with event data.
     * @return string
     */
    private function build_google_calendar_url( $booking ) {
        $start = gmdate( 'Ymd\THis\Z', strtotime( $booking->event_date . ' ' . $booking->event_time_start ) );
        $end   = gmdate( 'Ymd\THis\Z', strtotime( $booking->event_date . ' ' . $booking->event_time_end ) );

        $location = $booking->venue_name;
        if ( $booking->venue_address ) {
            $location .= ', ' . $booking->venue_address;
        }

        return add_query_arg( array(
            'action'   => 'TEMPLATE',
            'text'     => $booking->event_title,
            'dates'    => $start . '/' . $end,
            'details'  => '',
            'location' => $location,
        ), 'https://calendar.google.com/calendar/render' );
    }

    // -------------------------------------------------------------------------
    // [sws_waitlist_claim] — Waitlist claim page
    // -------------------------------------------------------------------------

    public function render_waitlist_claim( $atts ) {
        $this->do_enqueue( true );

        $token = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';

        if ( empty( $token ) ) {
            return '<p class="sws-notice">' . esc_html__( 'Invalid claim link.', 'sws-members-club' ) . '</p>';
        }

        return SWS_Template_Loader::render( 'waitlist-claim.php', array(
            'token' => $token,
        ) );
    }
}
