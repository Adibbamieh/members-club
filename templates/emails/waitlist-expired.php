<?php
/**
 * Email template: Waitlist Offer Expired.
 *
 * Variables: $member_name, $event_title, $event_date, $event_time, $venue_name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2><?php esc_html_e( 'Waitlist Offer Expired', 'sws-members-club' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'sws-members-club' ), esc_html( $member_name ) ); ?></p>

<p><?php printf( esc_html__( 'Unfortunately, your waitlist offer for %s has expired and the spot has been offered to the next person on the waitlist.', 'sws-members-club' ), '<strong>' . esc_html( $event_title ) . '</strong>' ); ?></p>

<dl class="email-detail">
    <dt><?php esc_html_e( 'Event', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_title ); ?></dd>

    <dt><?php esc_html_e( 'Date', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_date ); ?></dd>
</dl>

<p><?php esc_html_e( 'We hope to see you at a future event.', 'sws-members-club' ); ?></p>
