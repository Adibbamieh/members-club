<?php
/**
 * Email template: Booking Confirmation.
 *
 * Variables: $member_name, $event_title, $event_date, $event_time,
 *            $venue_name, $venue_address, $guest_name, $amount_paid,
 *            $booking_id, $gcal_url, $ics_url
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2><?php esc_html_e( 'Booking Confirmed', 'sws-members-club' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'sws-members-club' ), esc_html( $member_name ) ); ?></p>

<p><?php printf( esc_html__( 'Your ticket for %s has been confirmed.', 'sws-members-club' ), '<strong>' . esc_html( $event_title ) . '</strong>' ); ?></p>

<dl class="email-detail">
    <dt><?php esc_html_e( 'Event', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_title ); ?></dd>

    <dt><?php esc_html_e( 'Date', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_date ); ?></dd>

    <dt><?php esc_html_e( 'Time', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_time ); ?></dd>

    <?php if ( $venue_name ) : ?>
        <dt><?php esc_html_e( 'Venue', 'sws-members-club' ); ?></dt>
        <dd>
            <?php echo esc_html( $venue_name ); ?>
            <?php if ( $venue_address ) : ?>
                <br><?php echo esc_html( $venue_address ); ?>
            <?php endif; ?>
        </dd>
    <?php endif; ?>

    <?php if ( $guest_name ) : ?>
        <dt><?php esc_html_e( 'Guest', 'sws-members-club' ); ?></dt>
        <dd><?php echo esc_html( $guest_name ); ?></dd>
    <?php endif; ?>

    <?php if ( $amount_paid > 0 ) : ?>
        <dt><?php esc_html_e( 'Amount Paid', 'sws-members-club' ); ?></dt>
        <dd>&pound;<?php echo esc_html( number_format( $amount_paid, 2 ) ); ?></dd>
    <?php else : ?>
        <dt><?php esc_html_e( 'Price', 'sws-members-club' ); ?></dt>
        <dd><?php esc_html_e( 'Included with your membership', 'sws-members-club' ); ?></dd>
    <?php endif; ?>
</dl>

<div class="calendar-links">
    <strong><?php esc_html_e( 'Add to your calendar:', 'sws-members-club' ); ?></strong><br>
    <a href="<?php echo esc_url( $gcal_url ); ?>"><?php esc_html_e( 'Google Calendar', 'sws-members-club' ); ?></a>
    <a href="<?php echo esc_url( $ics_url ); ?>"><?php esc_html_e( 'Outlook / Apple Calendar (.ics)', 'sws-members-club' ); ?></a>
</div>

<p><?php esc_html_e( 'A calendar invite is also attached to this email.', 'sws-members-club' ); ?></p>

<p><?php esc_html_e( 'We look forward to seeing you there!', 'sws-members-club' ); ?></p>
