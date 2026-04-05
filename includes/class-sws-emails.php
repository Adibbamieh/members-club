<?php
/**
 * Email templates and sending.
 *
 * All emails use wp_mail with HTML templates.
 * Includes club logo and are mobile-friendly.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Emails {

    /**
     * Send booking confirmation email.
     *
     * @param int $booking_id Booking ID.
     */
    public static function send_booking_confirmation( $booking_id ) {
        $bookings = new SWS_Bookings();
        $booking  = $bookings->get( $booking_id );

        if ( ! $booking ) {
            return;
        }

        $user = get_userdata( $booking->member_user_id );
        if ( ! $user ) {
            return;
        }

        $to      = $user->user_email;
        $subject = sprintf(
            /* translators: %s: event title */
            __( 'Your ticket for %s is confirmed', 'sws-members-club' ),
            $booking->event_title
        );

        $vars = self::booking_template_vars( $booking, $user );
        $body = self::render_template( 'booking-confirmation.php', $vars );

        // Generate .ics attachment.
        $ics_content = SWS_Calendar::generate_ics( $booking );
        $ics_file    = wp_upload_dir()['basedir'] . '/sws-temp-' . $booking->id . '.ics';
        file_put_contents( $ics_file, $ics_content );

        $attachments = array( $ics_file );

        self::send( $to, $subject, $body, $attachments );

        // Cleanup temp file.
        @unlink( $ics_file );
    }

    /**
     * Send cancellation confirmation email.
     *
     * @param int    $booking_id Booking ID.
     * @param object $booking    Booking data (pre-cancellation state).
     */
    public static function send_cancellation_confirmation( $booking_id, $booking ) {
        $user = get_userdata( $booking->member_user_id );
        if ( ! $user ) {
            return;
        }

        $to      = $user->user_email;
        $subject = sprintf(
            /* translators: %s: event title */
            __( 'Your ticket for %s has been cancelled', 'sws-members-club' ),
            $booking->event_title
        );

        $vars = self::booking_template_vars( $booking, $user );
        $vars['refund_amount'] = (float) $booking->amount_paid;
        $body = self::render_template( 'cancellation-confirmation.php', $vars );

        self::send( $to, $subject, $body );
    }

    /**
     * Send event reminder email.
     *
     * @param int    $booking_id   Booking ID.
     * @param string $reminder_type Reminder type identifier (e.g. '48h', '24h', '2h').
     */
    public static function send_reminder( $booking_id, $reminder_type ) {
        $bookings = new SWS_Bookings();
        $booking  = $bookings->get( $booking_id );

        if ( ! $booking || $booking->status !== 'confirmed' || $booking->event_status !== 'published' ) {
            return;
        }

        $user = get_userdata( $booking->member_user_id );
        if ( ! $user ) {
            return;
        }

        $to      = $user->user_email;
        $subject = sprintf(
            /* translators: %s: event title */
            __( 'Reminder: %s is coming up', 'sws-members-club' ),
            $booking->event_title
        );

        $vars = self::booking_template_vars( $booking, $user );
        $body = self::render_template( 'reminder.php', $vars );

        self::send( $to, $subject, $body );
    }

    /**
     * Send waitlist offer email.
     *
     * @param int    $booking_id Booking ID.
     * @param string $claim_url  URL to claim the spot.
     * @param int    $hours      Hours until expiry.
     */
    public static function send_waitlist_offer( $booking_id, $claim_url, $hours ) {
        $bookings = new SWS_Bookings();
        $booking  = $bookings->get( $booking_id );

        if ( ! $booking ) {
            return;
        }

        $user = get_userdata( $booking->member_user_id );
        if ( ! $user ) {
            return;
        }

        $to      = $user->user_email;
        $subject = sprintf(
            /* translators: %s: event title */
            __( 'A spot has opened up for %s', 'sws-members-club' ),
            $booking->event_title
        );

        $vars              = self::booking_template_vars( $booking, $user );
        $vars['claim_url'] = $claim_url;
        $vars['hours']     = $hours;
        $body              = self::render_template( 'waitlist-offer.php', $vars );

        self::send( $to, $subject, $body );
    }

    /**
     * Send waitlist expiry email.
     *
     * @param int $booking_id Booking ID.
     */
    public static function send_waitlist_expired( $booking_id ) {
        $bookings = new SWS_Bookings();
        $booking  = $bookings->get( $booking_id );

        if ( ! $booking ) {
            return;
        }

        $user = get_userdata( $booking->member_user_id );
        if ( ! $user ) {
            return;
        }

        $to      = $user->user_email;
        $subject = sprintf(
            /* translators: %s: event title */
            __( 'Your waitlist offer for %s has expired', 'sws-members-club' ),
            $booking->event_title
        );

        $vars = self::booking_template_vars( $booking, $user );
        $body = self::render_template( 'waitlist-expired.php', $vars );

        self::send( $to, $subject, $body );
    }

    /**
     * Send penalty notice email.
     *
     * @param int    $user_id     WordPress user ID.
     * @param int    $strike_count Current strike count.
     * @param int    $max_strikes  Max strikes before restriction.
     * @param string $reason       Strike reason.
     */
    public static function send_penalty_notice( $user_id, $strike_count, $max_strikes, $reason ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $to = $user->user_email;

        if ( $strike_count >= $max_strikes ) {
            $subject = __( 'Your booking privileges have been restricted', 'sws-members-club' );
        } else {
            $subject = __( 'You have received a penalty strike', 'sws-members-club' );
        }

        $vars = array(
            'member_name'   => $user->display_name,
            'strike_count'  => $strike_count,
            'max_strikes'   => $max_strikes,
            'reason'        => $reason,
            'is_restricted'  => $strike_count >= $max_strikes,
        );

        $body = self::render_template( 'penalty-notice.php', $vars );

        self::send( $to, $subject, $body );
    }

    /**
     * Send event cancellation notification to all attendees.
     *
     * @param object $event    Event data.
     * @param array  $bookings Array of booking records.
     */
    public static function send_event_cancelled_notifications( $event, $bookings ) {
        $notified_users = array();

        foreach ( $bookings as $booking ) {
            // Avoid duplicate emails to the same user (if they had member + guest ticket).
            if ( in_array( $booking->member_user_id, $notified_users, true ) ) {
                continue;
            }

            $user = get_userdata( $booking->member_user_id );
            if ( ! $user ) {
                continue;
            }

            $subject = sprintf(
                /* translators: %s: event title */
                __( '%s has been cancelled', 'sws-members-club' ),
                $event->title
            );

            $vars = array(
                'member_name' => $user->display_name,
                'event_title' => $event->title,
                'event_date'  => date_i18n( get_option( 'date_format' ), strtotime( $event->event_date ) ),
                'event_time'  => date_i18n( 'g:i A', strtotime( $event->event_time_start ) ),
                'venue_name'  => $event->venue_name,
                'refund_amount' => (float) $booking->amount_paid,
            );

            $body = self::render_template( 'cancellation-confirmation.php', $vars );

            self::send( $user->user_email, $subject, $body );

            $notified_users[] = $booking->member_user_id;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build common template variables from a booking.
     */
    private static function booking_template_vars( $booking, $user ) {
        return array(
            'member_name'   => $user->display_name,
            'event_title'   => $booking->event_title,
            'event_date'    => date_i18n( get_option( 'date_format' ), strtotime( $booking->event_date ) ),
            'event_time'    => date_i18n( 'g:i A', strtotime( $booking->event_time_start ) ) . ' - ' . date_i18n( 'g:i A', strtotime( $booking->event_time_end ) ),
            'venue_name'    => $booking->venue_name,
            'venue_address' => $booking->venue_address,
            'guest_name'    => $booking->guest_name,
            'amount_paid'   => (float) $booking->amount_paid,
            'booking_id'    => $booking->id,
            'gcal_url'      => SWS_Calendar::google_calendar_url( $booking ),
            'ics_url'       => SWS_Calendar::ics_download_url( $booking->id ),
        );
    }

    /**
     * Render an email template file.
     *
     * @param string $template Template filename (in templates/emails/).
     * @param array  $vars     Variables to extract.
     * @return string Rendered HTML.
     */
    private static function render_template( $template, $vars = array() ) {
        $file = SWS_PLUGIN_DIR . 'templates/emails/' . $template;
        if ( ! file_exists( $file ) ) {
            return '';
        }

        extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract

        ob_start();
        include $file;
        $content = ob_get_clean();

        return self::wrap_html( $content );
    }

    /**
     * Wrap email content in the branded HTML shell.
     *
     * @param string $content Inner HTML content.
     * @return string Full HTML email.
     */
    private static function wrap_html( $content ) {
        $logo           = get_option( 'sws_club_logo', '' );
        $primary_colour = get_option( 'sws_primary_colour', '#333333' );

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #333; background: #f5f5f5; }
.email-wrapper { max-width: 600px; margin: 0 auto; background: #fff; }
.email-header { padding: 24px 32px; text-align: center; background: <?php echo esc_attr( $primary_colour ); ?>; }
.email-header img { max-height: 50px; width: auto; }
.email-body { padding: 32px; }
.email-body h2 { margin-top: 0; color: <?php echo esc_attr( $primary_colour ); ?>; }
.email-body p { line-height: 1.6; margin: 0 0 16px; }
.email-detail { background: #f9f9f9; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
.email-detail dt { font-weight: 600; margin-bottom: 2px; }
.email-detail dd { margin: 0 0 12px; }
.email-cta { display: inline-block; padding: 12px 28px; background: <?php echo esc_attr( $primary_colour ); ?>; color: #fff; text-decoration: none; border-radius: 4px; margin: 8px 4px; }
.email-footer { padding: 20px 32px; text-align: center; font-size: 13px; color: #999; border-top: 1px solid #eee; }
.calendar-links { margin: 16px 0; }
.calendar-links a { margin-right: 12px; color: <?php echo esc_attr( $primary_colour ); ?>; }
</style>
</head>
<body>
<div class="email-wrapper">
    <div class="email-header">
        <?php if ( $logo ) : ?>
            <img src="<?php echo esc_url( $logo ); ?>" alt="">
        <?php endif; ?>
    </div>
    <div class="email-body">
        <?php echo $content; ?>
    </div>
    <div class="email-footer">
        <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
    </div>
</div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send an email via wp_mail.
     *
     * @param string $to          Recipient email.
     * @param string $subject     Subject line.
     * @param string $body        HTML body.
     * @param array  $attachments File paths.
     */
    private static function send( $to, $subject, $body, $attachments = array() ) {
        $from_name    = get_option( 'sws_email_from_name', get_bloginfo( 'name' ) );
        $from_address = get_option( 'sws_email_from_address', get_bloginfo( 'admin_email' ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_address . '>',
        );

        wp_mail( $to, $subject, $body, $headers, $attachments );
    }
}
