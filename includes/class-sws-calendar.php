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
     * Generate .ics file content for a booking.
     *
     * @param object $booking Booking record with event data joined.
     * @return string ICS content.
     */
    public static function generate_ics( $booking ) {
        $uid       = 'sws-booking-' . $booking->id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
        $now       = gmdate( 'Ymd\THis\Z' );
        $start     = self::to_utc( $booking->event_date . ' ' . $booking->event_time_start );
        $end       = self::to_utc( $booking->event_date . ' ' . $booking->event_time_end );
        $summary   = self::ics_escape( $booking->event_title );
        $location  = self::ics_escape( self::build_location( $booking ) );

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

        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//SWS Members Club//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$uid}\r\n";
        $ics .= "DTSTAMP:{$now}\r\n";
        $ics .= "DTSTART:{$start}\r\n";
        $ics .= "DTEND:{$end}\r\n";
        $ics .= "SUMMARY:{$summary}\r\n";
        $ics .= "LOCATION:{$location}\r\n";
        $ics .= "DESCRIPTION:{$description}\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Build a Google Calendar URL for a booking.
     *
     * @param object $booking Booking with event data.
     * @return string
     */
    public static function google_calendar_url( $booking ) {
        $start    = gmdate( 'Ymd\THis\Z', strtotime( $booking->event_date . ' ' . $booking->event_time_start ) );
        $end      = gmdate( 'Ymd\THis\Z', strtotime( $booking->event_date . ' ' . $booking->event_time_end ) );
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
     * Convert a local datetime string to UTC format for ICS.
     *
     * @param string $datetime Local datetime string.
     * @return string UTC formatted: Ymd\THis\Z
     */
    private static function to_utc( $datetime ) {
        return gmdate( 'Ymd\THis\Z', strtotime( $datetime ) );
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
