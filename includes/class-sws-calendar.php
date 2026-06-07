<?php
/**
 * ICS calendar file generation and Google Calendar link builder.
 *
 * Each ticket generates its own calendar invite — never one invite for multiple tickets.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Calendar {

    /**
     * Generate a single-event .ics file for one booking (e.g. email attachment).
     *
     * @param object $booking Booking record with event data joined.
     * @return string ICS content.
     */
    public static function generate_ics( $booking ) {
        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//SWS Members Club//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= self::build_vevent( $booking );
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Generate a multi-event subscription feed (.ics) for a set of bookings.
     *
     * Used by the personal calendar subscription URL so a member's calendar app
     * auto-syncs all their upcoming bookings and re-polls periodically.
     *
     * @param array  $bookings Array of booking records with event data joined.
     * @param string $cal_name Display name for the calendar (X-WR-CALNAME).
     * @return string ICS content.
     */
    public static function generate_feed_ics( $bookings, $cal_name = '' ) {
        if ( empty( $cal_name ) ) {
            $cal_name = sprintf(
                /* translators: %s: site name */
                __( '%s — My Events', 'sws-members-club' ),
                get_bloginfo( 'name' )
            );
        }

        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//SWS Members Club//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= 'X-WR-CALNAME:' . self::ics_escape( $cal_name ) . "\r\n";
        // Hint calendar clients to re-poll roughly hourly.
        $ics .= "REFRESH-INTERVAL;VALUE=DURATION:PT1H\r\n";
        $ics .= "X-PUBLISHED-TTL:PT1H\r\n";

        foreach ( (array) $bookings as $booking ) {
            $ics .= self::build_vevent( $booking );
        }

        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Build a single VEVENT block for a booking (shared by single + feed output).
     *
     * @param object $booking Booking record with event data joined.
     * @return string VEVENT block.
     */
    private static function build_vevent( $booking ) {
        $uid      = 'sws-booking-' . $booking->id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
        $now      = gmdate( 'Ymd\THis\Z' );
        $start    = self::local_to_utc( $booking->event_date . ' ' . $booking->event_time_start );
        $end      = self::local_to_utc( $booking->event_date . ' ' . $booking->event_time_end );
        $summary  = self::ics_escape( $booking->event_title );
        $location = self::ics_escape( self::build_location( $booking ) );

        $description_parts = array();
        $description_parts[] = $booking->event_title;
        $description_parts[] = date_i18n( get_option( 'date_format' ), strtotime( $booking->event_date ) );
        $description_parts[] = date_i18n( 'g:i A', strtotime( $booking->event_time_start ) ) . ' - ' . date_i18n( 'g:i A', strtotime( $booking->event_time_end ) );
        if ( $booking->venue_name ) {
            $description_parts[] = $booking->venue_name;
        }
        if ( $booking->guest_name ) {
            $description_parts[] = sprintf( __( 'Guest: %s', 'sws-members-club' ), $booking->guest_name );
        }
        $description = self::ics_escape( implode( '\n', $description_parts ) );

        $vevent  = "BEGIN:VEVENT\r\n";
        $vevent .= "UID:{$uid}\r\n";
        $vevent .= "DTSTAMP:{$now}\r\n";
        $vevent .= "DTSTART:{$start}\r\n";
        $vevent .= "DTEND:{$end}\r\n";
        $vevent .= "SUMMARY:{$summary}\r\n";
        $vevent .= "LOCATION:{$location}\r\n";
        $vevent .= "DESCRIPTION:{$description}\r\n";
        $vevent .= "STATUS:CONFIRMED\r\n";
        $vevent .= "END:VEVENT\r\n";

        return $vevent;
    }

    /**
     * Build a Google Calendar URL for a booking.
     *
     * @param object $booking Booking with event data.
     * @return string
     */
    public static function google_calendar_url( $booking ) {
        $start    = self::local_to_utc( $booking->event_date . ' ' . $booking->event_time_start );
        $end      = self::local_to_utc( $booking->event_date . ' ' . $booking->event_time_end );
        $location = self::build_location( $booking );

        return add_query_arg( array(
            'action'   => 'TEMPLATE',
            'text'     => $booking->event_title,
            'dates'    => $start . '/' . $end,
            'details'  => '',
            'location' => $location,
        ), 'https://calendar.google.com/calendar/render' );
    }

    /**
     * Build a download URL for a booking .ics file.
     *
     * @param int $booking_id Booking ID.
     * @return string
     */
    public static function ics_download_url( $booking_id ) {
        return rest_url( 'sws/v1/calendar/' . $booking_id . '.ics' );
    }

    /**
     * Build location string from booking data.
     *
     * @param object $booking Booking with venue_name and venue_address.
     * @return string
     */
    private static function build_location( $booking ) {
        $parts = array();
        if ( ! empty( $booking->venue_name ) ) {
            $parts[] = $booking->venue_name;
        }
        if ( ! empty( $booking->venue_address ) ) {
            $parts[] = $booking->venue_address;
        }
        return implode( ', ', $parts );
    }

    /**
     * Convert a WordPress-local datetime string to UTC format for ICS.
     *
     * Treats the input datetime as being in the WordPress site's timezone
     * (Settings > General > Timezone), then converts to UTC. This avoids the
     * "event shows 1 hour off" bug when the server is UTC but WordPress is BST.
     *
     * @param string $datetime Datetime string in WordPress site timezone (Y-m-d H:i:s).
     * @return string UTC-formatted: Ymd\THis\Z
     */
    private static function local_to_utc( $datetime ) {
        try {
            $tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
            $dt = new \DateTime( $datetime, $tz );
            $dt->setTimezone( new \DateTimeZone( 'UTC' ) );
            return $dt->format( 'Ymd\THis\Z' );
        } catch ( \Exception $e ) {
            // Fallback to old behaviour if datetime parsing fails.
            return gmdate( 'Ymd\THis\Z', strtotime( $datetime ) );
        }
    }

    /**
     * Escape special characters for ICS format.
     *
     * @param string $text Text to escape.
     * @return string
     */
    private static function ics_escape( $text ) {
        $text = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,' ), $text );
        return $text;
    }
}
