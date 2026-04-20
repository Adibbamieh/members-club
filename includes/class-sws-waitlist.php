<?php
/**
 * Waitlist promotion logic.
 *
 * When a confirmed booking is cancelled and a spot opens:
 * 1. Find the first waitlisted booking by position.
 * 2. Send "spot available" email with unique claim link.
 * 3. Set waitlist_offered_at to current time.
 * 4. Member has configurable hours to claim.
 * 5. If claimed: process payment (if needed), confirm booking, send confirmation.
 * 6. If expired: notify, move to next person.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Waitlist {

    /**
     * Promote the next person on the waitlist for an event.
     * Called when a confirmed booking is cancelled.
     *
     * @param int $event_id Event ID.
     */
    public static function promote_next( $event_id ) {
        global $wpdb;

        $bookings_table = SWS_Database::table( 'bookings' );

        // Find next waitlisted booking (lowest position, not yet offered).
        $next = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$bookings_table}
             WHERE event_id = %d
             AND status = 'waitlisted'
             AND waitlist_offered_at IS NULL
             ORDER BY waitlist_position ASC
             LIMIT 1",
            $event_id
        ) );

        if ( ! $next ) {
            return; // No one on waitlist.
        }

        // Generate unique claim token.
        $token = wp_generate_password( 32, false );

        // Update booking with offer.
        $wpdb->update( $bookings_table, array(
            'waitlist_offered_at' => current_time( 'mysql' ),
            'waitlist_claim_token' => $token,
        ), array( 'id' => $next->id ) );

        // Build claim URL.
        // Uses a page with [sws_waitlist_claim] shortcode — admin configures which page.
        $claim_page = get_option( 'sws_policy_page_url', home_url() );
        $claim_url  = add_query_arg( 'token', $token, home_url( '/' ) );

        // Try to find a page with the waitlist claim shortcode.
        global $wpdb;
        $claim_page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_content LIKE '%[sws_waitlist_claim]%'
             LIMIT 1"
        );
        if ( $claim_page_id ) {
            $claim_url = add_query_arg( 'token', $token, get_permalink( $claim_page_id ) );
        }

        $hours = (int) get_option( 'sws_waitlist_claim_hours', 12 );

        // Send offer email.
        SWS_Emails::send_waitlist_offer( $next->id, $claim_url, $hours );

        do_action( 'sws_waitlist_offer_sent', $next->id, $event_id, $token );
    }

    /**
     * Claim a waitlist spot by token.
     *
     * @param string $token          Claim token.
     * @param string $payment_intent_id Stripe PaymentIntent ID (if payment needed).
     * @return array|WP_Error { booking_id, event_id } on success.
     */
    public static function claim( $token, $payment_intent_id = '' ) {
        global $wpdb;

        $bookings_table = SWS_Database::table( 'bookings' );

        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*, e.title AS event_title, e.event_date, e.event_time_start, e.event_time_end,
                    e.venue_name, e.venue_address, e.ticket_price, e.currency, e.status AS event_status
             FROM {$bookings_table} b
             LEFT JOIN " . SWS_Database::table( 'events' ) . " e ON b.event_id = e.id
             WHERE b.waitlist_claim_token = %s
             AND b.status = 'waitlisted'",
            $token
        ) );

        if ( ! $booking ) {
            return new \WP_Error( 'invalid_token', __( 'This claim link is invalid or has already been used.', 'sws-members-club' ) );
        }

        // Check expiry.
        $hours     = (int) get_option( 'sws_waitlist_claim_hours', 12 );
        $offered   = strtotime( $booking->waitlist_offered_at );
        $expiry    = $offered + ( $hours * 3600 );

        if ( current_time( 'timestamp' ) > $expiry ) {
            return new \WP_Error( 'offer_expired', __( 'This waitlist offer has expired.', 'sws-members-club' ) );
        }

        // Check if event is still published.
        if ( $booking->event_status !== 'published' ) {
            return new \WP_Error( 'event_unavailable', __( 'This event is no longer available.', 'sws-members-club' ) );
        }

        // Determine if payment is needed.
        $members = new SWS_Members();
        $tiers   = new SWS_Tiers();
        $member  = $members->get_by_user_id( $booking->member_user_id );

        $events_included = $member && $tiers->tier_includes_events( $member->membership_tier_id );
        $needs_payment   = ! $events_included && (float) $booking->ticket_price > 0;

        $amount_paid = 0.00;

        if ( $needs_payment ) {
            if ( empty( $payment_intent_id ) ) {
                return new \WP_Error( 'payment_required', __( 'Payment is required to claim this spot.', 'sws-members-club' ) );
            }

            // Verify payment.
            $confirmed = SWS_Stripe::confirm_payment( $payment_intent_id );
            if ( ! $confirmed ) {
                return new \WP_Error( 'payment_failed', __( 'Payment could not be confirmed.', 'sws-members-club' ) );
            }

            $amount_paid = (float) $booking->ticket_price;
        }

        // Confirm the booking.
        $wpdb->update( $bookings_table, array(
            'status'                   => 'confirmed',
            'stripe_payment_intent_id' => $payment_intent_id ?: null,
            'amount_paid'              => $amount_paid,
            'booked_at'                => current_time( 'mysql' ),
            'waitlist_claim_token'     => null,
        ), array( 'id' => $booking->id ) );

        // Send booking confirmation.
        SWS_Emails::send_booking_confirmation( $booking->id );

        do_action( 'sws_waitlist_claimed', $booking->id, $booking->event_id );

        return array(
            'booking_id' => $booking->id,
            'event_id'   => $booking->event_id,
        );
    }

    /**
     * Check for expired waitlist offers and promote the next person.
     * Called by WP Cron every 15 minutes.
     */
    public static function check_expired_offers() {
        global $wpdb;

        $bookings_table = SWS_Database::table( 'bookings' );
        $hours          = (int) get_option( 'sws_waitlist_claim_hours', 12 );
        $cutoff         = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $hours * 3600 ) );

        // Find waitlisted bookings that were offered but not claimed within the window.
        $expired = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$bookings_table}
             WHERE status = 'waitlisted'
             AND waitlist_offered_at IS NOT NULL
             AND waitlist_offered_at < %s
             AND waitlist_claim_token IS NOT NULL",
            $cutoff
        ) );

        foreach ( $expired as $booking ) {
            // Clear the offer.
            $wpdb->update( $bookings_table, array(
                'waitlist_claim_token'  => null,
                'waitlist_offered_at'   => null,
                'status'                => 'cancelled',
                'cancelled_at'          => current_time( 'mysql' ),
            ), array( 'id' => $booking->id ) );

            // Send expiry notification.
            SWS_Emails::send_waitlist_expired( $booking->id );

            // Promote the next person.
            self::promote_next( $booking->event_id );

            do_action( 'sws_waitlist_offer_expired', $booking->id, $booking->event_id );
        }
    }
}
