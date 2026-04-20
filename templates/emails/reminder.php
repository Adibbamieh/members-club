<?php
/**
 * Email template: Event Reminder.
 *
 * Variables: $member_name, $event_title, $event_date, $event_time,
 *            $venue_name, $venue_address, $gcal_url, $ics_url
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2><?php printf( esc_html__( 'Reminder: %s', 'sws-members-club' ), esc_html( $event_title ) ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'sws-members-club' ), esc_html( $member_name ) ); ?></p>

<p><?php printf( esc_html__( 'This is a friendly reminder that %s is coming up soon.', 'sws-members-club' ), '<strong>' . esc_html( $event_title ) . '</strong>' ); ?></p>

<dl class="email-detail">
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
</dl>

<div class="calendar-links">
    <a href="<?php echo esc_url( $gcal_url ); ?>"><?php esc_html_e( 'Add to Google Calendar', 'sws-members-club' ); ?></a>
    <a href="<?php echo esc_url( $ics_url ); ?>"><?php esc_html_e( 'Download .ics', 'sws-members-club' ); ?></a>
</div>

<p><?php esc_html_e( 'See you there!', 'sws-members-club' ); ?></p>
